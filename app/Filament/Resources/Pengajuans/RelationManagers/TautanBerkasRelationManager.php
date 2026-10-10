<?php

namespace App\Filament\Resources\Pengajuans\RelationManagers;

use App\Actions\Berkas\HapusTautanBerkas;
use App\Actions\Berkas\SimpanTautanBerkas;
use App\Enums\JenisTautan;
use App\Exceptions\KesalahanDomain;
use App\Filament\Forms\IsianTautanBerkas;
use App\Models\Pengajuan;
use App\Models\TautanBerkas;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tautan TOR/KAK & surat tugas pengajuan. Tambah/hapus hanya selama pengajuan dapat diubah.
 */
class TautanBerkasRelationManager extends RelationManager
{
    protected static string $relationship = 'tautanBerkas';

    protected static ?string $title = 'TOR/KAK & tautan pendukung';

    protected static ?string $modelLabel = 'Tautan';

    /** @var list<JenisTautan> */
    public const JENIS = [JenisTautan::Tor, JenisTautan::SuratTugas, JenisTautan::Lainnya];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    /** Aksi tetap tersedia di halaman lihat; otorisasi per aksi lewat Policy pemilik. */
    public function isReadOnly(): bool
    {
        return false;
    }

    protected function getCreateAuthorizationResponse(): Response
    {
        return $this->bolehUbah() ? Response::allow() : Response::deny();
    }

    protected function bolehUbah(): bool
    {
        return auth()->user()?->can('update', $this->getOwnerRecord()) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(IsianTautanBerkas::skema(self::JENIS));
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('jenis', array_map(fn (JenisTautan $j) => $j->value, self::JENIS)))
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label')->label('Label')->wrap(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('penyedia')->label('Penyedia'),
                TextColumn::make('status_cek')->label('Status cek')->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah tautan')
                    ->visible(fn (): bool => $this->bolehUbah())
                    ->using(function (array $data): TautanBerkas {
                        /** @var Pengajuan $pengajuan */
                        $pengajuan = $this->getOwnerRecord();
                        /** @var User $pengguna */
                        $pengguna = auth()->user();

                        try {
                            return app(SimpanTautanBerkas::class)->handle(
                                $pengajuan,
                                JenisTautan::from((string) $data['jenis']),
                                (string) $data['url'],
                                (string) $data['label'],
                                (bool) ($data['akses_terbatas_dikonfirmasi'] ?? false),
                                $pengguna,
                            );
                        } catch (KesalahanDomain $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                Action::make('buka')
                    ->label('Buka berkas')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->authorize('view')
                    ->url(fn (TautanBerkas $record): string => $record->url)
                    ->extraAttributes(['rel' => 'noopener noreferrer'])
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->visible(fn (): bool => $this->bolehUbah())
                    ->using(function (TautanBerkas $record): bool {
                        /** @var User $pengguna */
                        $pengguna = auth()->user();
                        app(HapusTautanBerkas::class)->handle($record, $pengguna);

                        return true;
                    }),
            ]);
    }
}
