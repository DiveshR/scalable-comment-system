<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopPostsWidget extends TableWidget
{
    public static function canView(): bool
    {
        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin';
    }

    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Top 10 Most Discussed Posts';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Post::query()
                    ->withCount('comments')
                    ->orderByDesc('comments_count')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('title')
                    ->description(fn (Post $record) => "Author: {$record->user->name}")
                    ->searchable(),
                TextColumn::make('comments_count')
                    ->label('Total Comments')
                    ->sortable()
                    ->badge()
                    ->color('amber'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Posted'),
            ])
            ->paginated(false);
    }
}
