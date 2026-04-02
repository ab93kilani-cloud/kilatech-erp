<?php

namespace App\Imports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;

class SupplierImport implements ToModel, WithHeadingRow, WithUpserts
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Supplier([
            'name'     => $row['name'] ?? $row['nom'],
            'email'    => $row['email'],
            'phone'    => $row['phone'] ?? $row['telephone'],
            'address'  => $row['address'] ?? $row['adresse'],
        ]);
    }

    /**
     * @return string|array
     */
    public function uniqueBy()
    {
        return 'name'; // Detect duplicates based on the Name column and update instead of insert.
    }
}
