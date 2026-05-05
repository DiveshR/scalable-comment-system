<?php

namespace App\Filament\Widgets;

use App\Models\Comment;
use App\Models\Post;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsOverview extends BaseWidget
{
    public static function canView(): bool
    {
        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'app';
    }

    protected function getStats(): array
    {
        $userId = auth()->id();

        return [
            Stat::make('My Posts', Post::where('user_id', $userId)->count())
                ->description('Total discussions started')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('amber'),
            Stat::make('My Comments', Comment::where('user_id', $userId)->count())
                ->description('Total contributions made')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('primary'),
        ];
    }
}
