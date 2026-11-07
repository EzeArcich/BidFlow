<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        return view('organizations.index', ['organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['organization_id' => ['required', 'ulid']]);
        $organization = $request->user()->organizations()->where('organizations.status', 'active')->whereKey($data['organization_id'])->first();
        abort_unless($organization, 403);
        $request->session()->put('active_organization_id', $organization->id);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
