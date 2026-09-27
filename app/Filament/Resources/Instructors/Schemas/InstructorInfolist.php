<?php

namespace App\Filament\Resources\Instructors\Schemas;

use App\Models\User;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class InstructorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Instructor Profile')
                    ->components([
                        Text::make('name')
                            ->state(fn(User $record): string => "Name: {$record->name}"),

                        Text::make('email')
                            ->state(fn(User $record): string => "Email: {$record->email}"),

                        Text::make('iban_account')
                            ->state(fn(User $record): string => "IBAN / Payout Account: " . ($record->iban_account ?? 'Not set')),
                    ]),

                Section::make('Real-Time Ledger Balances')
                    ->components([
                        Text::make('owed_balance')
                            ->state(fn(User $record): string => "Outstanding Owed Balance: $" . number_format($record->getOwedBalanceInCents() / 100, 2)),

                        Text::make('total_paid')
                            ->state(fn(User $record): string => "Total Paid Out: $" . number_format($record->getTotalPaidInCents() / 100, 2)),

                        Text::make('total_earned')
                            ->state(fn(User $record): string => "Total Lifetime Earned: $" . number_format($record->getTotalEarnedInCents() / 100, 2)),
                    ]),
            ]);
    }
}
