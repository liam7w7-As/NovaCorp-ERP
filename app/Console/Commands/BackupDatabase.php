<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('backup:database {--conservar=10 : Cantidad de respaldos a conservar}')]
#[Description('Respalda la base de datos MySQL con mysqldump')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $dump = env('DB_DUMP_PATH', 'C:\\xampp\\mysql\\bin\\mysqldump.exe');
        if (! is_file($dump)) {
            // Buscar en PATH como alternativa
            $dump = 'mysqldump';
        }

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $nombre = 'respaldo-'.date('Ymd-His').'.sql';
        $ruta = Storage::path('respaldos/'.$nombre);
        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }

        // MYSQL_PWD evita exponer la contraseña en la línea de comandos
        $cmd = '"'.$dump.'" --host='.escapeshellarg($host).' --port='.escapeshellarg((string) $port)
            .' --user='.escapeshellarg($user).' --single-transaction --routines --triggers '
            .escapeshellarg($db).' 2>&1';

        $descriptor = [1 => ['file', $ruta, 'w'], 2 => ['file', $ruta, 'a']];
        $pwdAnterior = getenv('MYSQL_PWD');
        putenv('MYSQL_PWD='.(string) $pass);
        $proc = proc_open($cmd, $descriptor, $pipes);
        if ($pwdAnterior === false) {
            putenv('MYSQL_PWD');
        } else {
            putenv('MYSQL_PWD='.$pwdAnterior);
        }
        if (! is_resource($proc)) {
            $this->error('No se pudo iniciar mysqldump.');

            return self::FAILURE;
        }
        $codigo = proc_close($proc);

        if ($codigo !== 0 || ! is_file($ruta) || filesize($ruta) < 100) {
            @unlink($ruta);
            $this->error('mysqldump falló. Revisa DB_DUMP_PATH y las credenciales.');

            return self::FAILURE;
        }

        // Retención: conservar solo los N más recientes
        $conservar = max(1, (int) $this->option('conservar'));
        $archivos = collect(Storage::files('respaldos'))
            ->filter(fn ($f) => str_ends_with($f, '.sql'))
            ->sortDesc()
            ->values();
        foreach ($archivos->slice($conservar) as $viejo) {
            Storage::delete($viejo);
        }

        $this->info("Respaldo creado: {$nombre} (".number_format(filesize($ruta) / 1024, 1).' KB)');

        return self::SUCCESS;
    }
}
