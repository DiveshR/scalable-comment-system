<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Pages\PostCommentsPage;
use App\Filament\Resources\Posts\Pages\ManagePosts;
use App\Models\Post;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('body')
                    ->required()
                    ->rows(5),
                \Filament\Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id())
                    ->required(),
                \Filament\Forms\Components\Toggle::make('is_visible')
                    ->label('Visible')
                    ->default(true)
                    ->visible(fn () => \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Author')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                \Filament\Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean()
                    ->sortable()
                    ->visible(fn () => \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin'),
            ])
            ->actions([
                // The primary requirement: View comments for this post
                Action::make('comments')
                    ->label('Comments')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->color('primary')
                    ->url(fn (Post $record): string => PostCommentsPage::getUrl(['post' => $record->id], panel: 'app')),
                
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        // If we are in the 'app' panel, only show the user's own posts
        if (\Filament\Facades\Filament::getCurrentPanel()?->getId() === 'app') {
            return $query->where('user_id', auth()->id());
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePosts::route('/'),
        ];
    }
}
