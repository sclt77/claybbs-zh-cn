<?php

namespace App\Controllers\Api;

use App\Models\AnnouncementModel;

class AnnouncementController
{
    private function visitorKey(): string
    {
        if (empty($_COOKIE['clay_visitor_key'])) {
            $key = bin2hex(random_bytes(12));
            setcookie('clay_visitor_key', $key, time()+86400*365, '/');
            $_COOKIE['clay_visitor_key'] = $key;
        }
        return preg_replace('/[^a-zA-Z0-9]/', '', (string)$_COOKIE['clay_visitor_key']) ?: '';
    }

    public function popup(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $msg = (new AnnouncementModel())->popupForUser((int)($_SESSION['auth_user']['id'] ?? 0), $this->visitorKey());
            echo json_encode(['ok'=>true,'announcement'=>$msg], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) { echo json_encode(['ok'=>false], JSON_UNESCAPED_UNICODE); }
    }

    public function read(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        csrf_verify();
        (new AnnouncementModel())->markRead((int)($_POST['id'] ?? 0), (int)($_SESSION['auth_user']['id'] ?? 0), $this->visitorKey());
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
    }
}
