<?php

namespace App\Livewire\Auth;

use App\Livewire\Forms\LoginForm;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::guest')]
#[Title('Log in')]
class Login extends Component
{
    public LoginForm $form;

    public function login()
    {
        $this->form->authenticate();
        session()->regenerate();
        session()->flash('toast', ['type' => 'success', 'message' => 'Welcome back.']);

        return $this->redirectIntended(route('home'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
