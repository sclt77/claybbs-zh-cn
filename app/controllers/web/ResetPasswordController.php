<?php

namespace App\Controllers\Web;

use App\Models\UserModel;
use App\Core\Mailer;

class ResetPasswordController
{
    public function index(): void
    {
        $token = trim($_GET['token'] ?? '');
        $error = '';
        $success = '';
        $user = null;
        
        if ($token === '') {
            $error = '重置链接无效';
        } else {
            try {
                $userModel = new UserModel();
                $user = $userModel->findByResetToken($token);
                
                if (!$user) {
                    $error = '重置链接无效或已过期';
                }
            } catch (\Throwable $e) {
                error_log('[ClayBBS] 密码重置验证错误: ' . $e->getMessage());
                $error = '验证链接时出错，请稍后再试';
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
            csrf_verify();
            
            $password = trim($_POST['password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');
            
            if ($password === '' || $confirmPassword === '') {
                $error = '请填写完整密码信息';
            } elseif ($password !== $confirmPassword) {
                $error = '两次输入的密码不一致';
            } elseif (strlen($password) < 6) {
                $error = '密码长度至少6位';
            } else {
                try {
                    
                    $userModel = new UserModel();
                    $userModel->resetPassword((int)$user['id'], $password);
                    
                    
                    $userModel->clearPasswordResetToken((int)$user['id']);
                    
                    $success = '密码重置成功！';
                } catch (\Throwable $e) {
                    error_log('[ClayBBS] 密码重置错误: ' . $e->getMessage());
                    $error = '重置密码时出错，请稍后再试';
                }
            }
        }
        
        require theme_view('web/user/reset_password.php');
    }
}