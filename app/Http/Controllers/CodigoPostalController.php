<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CodigoPostalController extends Controller
{
    public function codigoPostal(Request $request)
    {

        $data = $request->validate([
            'postCode' => 'required|string',
            'token' => ['required', 'string', 'in:sZQe4cxaEWeFBe3EPkeah0KqowVBLx']
        ]);
    }
}
