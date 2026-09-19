<?php

test('the welcome page is public', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

test('guest login entry points redirect into the accounts sso flow', function () {
    $this->get(route('login'))
        ->assertRedirect(route('auth.accounts.redirect'));

    $this->get(route('register'))
        ->assertRedirect(route('auth.accounts.redirect'));
});
