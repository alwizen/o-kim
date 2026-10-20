<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class PendingInternalController extends Controller
{
    /**
     * Halaman tunggu untuk pengguna internal yang belum disetujui super admin.
     */
    public function show()
    {
        return view('landing.pending-internal');
    }
}
