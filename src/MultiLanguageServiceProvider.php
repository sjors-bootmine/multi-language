<?php

namespace OpenAdmin\MultiLanguage;

use Illuminate\Support\ServiceProvider;

class MultiLanguageServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot(MultiLanguage $extension)
    {
        if (! MultiLanguage::boot()) {
            return ;
        }

        if ($views = $extension->views()) {
            $this->loadViewsFrom($views, 'multi-language');
        }

        if ($this->app->runningInConsole() && $assets = $extension->assets()) {
            $this->publishes(
                [$assets => public_path('vendor/open-admin-ext/multi-language')],
                'multi-language'
            );
        }

        $this->app->booted(function () {
            MultiLanguage::routes(__DIR__.'/../routes/web.php');
        });
    }
}