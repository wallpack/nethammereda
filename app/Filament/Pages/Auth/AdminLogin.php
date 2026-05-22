<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class AdminLogin extends Login
{
    protected string $view = 'filament.pages.auth.admin-login';

    protected Width|string|null $maxWidth = Width::ScreenTwoExtraLarge;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                Group::make([
                    $this->getRememberFormComponent(),
                    SchemaView::make('filament.pages.auth.partials.admin-login-forgot-password'),
                ])
                    ->columns(2)
                    ->extraAttributes(['class' => 'nh-admin-login-options']),
            ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Вход в админ-панель';
    }

    public function hasLogo(): bool
    {
        return false;
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Email')
            ->placeholder('Введите email')
            ->prefixIcon('heroicon-m-envelope')
            ->extraInputAttributes(['data-testid' => 'admin-login-email'])
            ->extraFieldWrapperAttributes(['class' => 'nh-admin-login-field nh-admin-login-field--email']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Пароль')
            ->placeholder('Введите пароль')
            ->prefixIcon('heroicon-m-lock-closed')
            ->extraInputAttributes(['data-testid' => 'admin-login-password'])
            ->extraFieldWrapperAttributes(['class' => 'nh-admin-login-field nh-admin-login-field--password']);
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Запомнить меня')
            ->default(false)
            ->extraInputAttributes(['data-testid' => 'admin-login-remember'])
            ->extraFieldWrapperAttributes(['class' => 'nh-admin-login-remember']);
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Войти в систему');
    }
}
