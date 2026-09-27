<?php

namespace App\Filament\Resources\Instructors\Tables;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InstructorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Instructor Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('iban_account')
                    ->label('Payout IBAN / Account')
                    ->placeholder('No IBAN set')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('owed_balance')
                    ->label('Outstanding / Owed')
                    ->getStateUsing(fn(User $record): string => '$' . number_format($record->getOwedBalanceInCents() / 100, 2))
                    ->badge()
                    ->color('warning')
                    ->sortable(false),

                TextColumn::make('total_paid')
                    ->label('Total Paid')
                    ->getStateUsing(fn(User $record): string => '$' . number_format($record->getTotalPaidInCents() / 100, 2))
                    ->badge()
                    ->color('success')
                    ->sortable(false),

                TextColumn::make('total_earned')
                    ->label('Lifetime Earned')
                    ->getStateUsing(fn(User $record): string => '$' . number_format($record->getTotalEarnedInCents() / 100, 2))
                    ->badge()
                    ->color('info')
                    ->sortable(false),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
