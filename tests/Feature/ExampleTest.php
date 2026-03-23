<?php

test('ルートへのアクセスが未認証ユーザーをログインページへリダイレクトする', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
