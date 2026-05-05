<?php

namespace App\Filament\Widgets;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    public static function canView(): bool
    {
        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin';
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::where('is_admin', false)->count())
                ->description('Registered customers')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),
            Stat::make('Total Posts', Post::count())
                ->description('Community discussions')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
            Stat::make('Total Comments', Comment::count())
                ->description('User interactions')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('amber'),
        ];
    }
}
