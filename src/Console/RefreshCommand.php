<?php
namespace JsLocalization\Console;

use Config;
use Illuminate\Console\Command;
use File;
use JsLocalization\Exceptions\ConfigException;
use JsLocalization\Facades\ConfigCachingService;
use JsLocalization\Facades\MessageCachingService;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class RefreshCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'js-localization:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Refresh message cache after changing the config file";

    /**
     * Execute the console command.
     *
     * @return void
     * @throws ConfigException
     */
    public function fire()
    {
        $this->line('Refreshing the message cache...');

        $locales = Config::get('js-localization.locales');

        if(!is_array($locales)) {
          throw new ConfigException('Please set the "locales" config! See https://github.com/andywer/laravel-js-localization#configuration');
        }

        switch (Config::get('js-localization.storage')) {
            case 'file':
                $this->generateMessagesFile(Config::get('js-localization.storage_path'));
                $this->generateConfigFile(Config::get('js-localization.storage_path'));
                break;
            // Cache is also the default option.
            case 'cache':
            default:
                MessageCachingService::refreshCache();
                ConfigCachingService::refreshCache();
                break;
        }
    }

    /**
     * Generate the messages file.
     *
     * @param string $path
     */
    public function generateMessagesFile($path)
    {
        $messages = MessageCachingService::getMessagesJson();
        $messages = $this->ensureBackwardsCompatibility($messages);

        $contents  = 'Lang.addMessages(' . $messages . ');';

        if (!is_dir($path)) {
            mkdir($path, '0777', true);
        }
        $path = $path . 'messages';
        File::put($path, $contents);

        $this->line("Generated $path");
    }

    /**
     * Generage the config file.
     *
     * @param string $path
     */
    public function generateConfigFile($path)
    {
        $config = ConfigCachingService::getConfigJson();
        if ($config === '{}') {
            return;
        }

        $contents = 'Config.addConfig(' . $config . ');';

        if (!is_dir($path)) {
            mkdir($path, '0777', true);
        }
        $path = $path . 'config';
        File::put($path, $contents);

        $this->line("Generated $path");
    }

    /**
     * Transforms the cached data to stay compatible to old versions of the package.
     *
     * @param string $messages
     * @return string
     */
    protected function ensureBackwardsCompatibility($messages)
    {
        if (preg_match('/^\\{"[a-z]{2}":/', $messages)) {
            return $messages;
        } else {
            return '{"' . app()->getLocale() . '":' . $messages . '}';
        }
    }
}
