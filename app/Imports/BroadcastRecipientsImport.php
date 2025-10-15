<?php

namespace App\Imports;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BroadcastRecipientsImport
{
    public static function importFromExcel(string $filePath)
    {
        // Baca file Excel
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $headerSkipped = false;
        $imported = 0;

        foreach ($rows as $row) {
            // Lewati baris header pertama
            if (!$headerSkipped) {
                $headerSkipped = true;
                continue;
            }

            // Ambil kolom (sesuaikan urutannya dengan file Excel)
            $namaPerusahaan = trim($row[0] ?? '');
            $pic = trim($row[1] ?? '');
            $email = trim($row[2] ?? '');

            // Validasi email sederhana
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Simpan ke database
            DB::table('broadcast_recipients')->updateOrInsert(
                ['email' => $email],
                [
                    'id' => Str::uuid()->toString(),
                    'nama_perusahaan' => $namaPerusahaan,
                    'pic' => $pic,
                    'is_subscribed' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );

            $imported++;
        }

        return $imported;
    }
}
