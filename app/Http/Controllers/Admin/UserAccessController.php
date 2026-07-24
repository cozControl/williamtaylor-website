<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;

final class UserAccessController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index');
    }

    public function show(User $user): View
    {
        return view('admin.users.show', compact('user'));
    }
}
