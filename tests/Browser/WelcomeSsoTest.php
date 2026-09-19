<?php

test('homepage login and get started use full-page links to sso', function () {
    $page = visit('/');

    $page->assertAttribute('@sso-login', 'href', '/login')
        ->assertAttribute('@sso-get-started', 'href', '/login')
        ->assertAttribute('@sso-start', 'href', '/login')
        ->assertAttribute('@sso-login-secondary', 'href', '/login')
        ->assertNoJavaScriptErrors();
});

test('clicking log in on the homepage navigates away from the welcome page', function () {
    $page = visit('/');

    // Plain <a href="/login"> must full-page navigate. An Inertia <Link> to /login
    // would soft-navigate into the OIDC redirect chain and appear to do nothing.
    $page->click('@sso-login')
        ->assertPathIsNot('/')
        ->assertNoJavaScriptErrors();
});

test('clicking get started on the homepage navigates away from the welcome page', function () {
    $page = visit('/');

    $page->click('@sso-get-started')
        ->assertPathIsNot('/')
        ->assertNoJavaScriptErrors();
});
