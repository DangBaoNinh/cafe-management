<?php

use Livewire\Component;

new class extends Component {
    public function deleteUser(): never
    {
        abort(403, __('Account deletion is currently disabled.'));
    }
}; ?>

<div></div>
