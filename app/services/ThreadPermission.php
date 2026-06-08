<?php

namespace App\Services;

use App\Middleware\Permission;

class ThreadPermission
{
    public static function sectionId(array $thread): int
    {
        return (int)($thread['section_id'] ?? 0);
    }

    public static function categoryId(array $thread): ?int
    {
        $categoryId = (int)($thread['category_id'] ?? 0);
        return $categoryId > 0 ? $categoryId : null;
    }

    public static function can(string $perm, array $thread, bool $includeCategory = false): bool
    {
        $sectionId = self::sectionId($thread);
        if (Permission::can($perm) || Permission::can($perm, 'section', $sectionId)) {
            return true;
        }
        return $includeCategory && Permission::can($perm, 'category', self::categoryId($thread));
    }

    public static function canEdit(array $thread): bool
    {
        $userId = (int)(auth_user()['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }
        return (int)($thread['user_id'] ?? 0) === $userId || self::can('thread.edit_any', $thread);
    }

    public static function canHide(array $thread, bool $includeCategory = false): bool
    {
        return self::can('thread.hide', $thread, $includeCategory);
    }

    public static function canDelete(array $thread, bool $includeCategory = false): bool
    {
        return self::can('thread.delete_any', $thread, $includeCategory);
    }

    public static function canPin(array $thread, bool $includeCategory = false): bool
    {
        return self::can('thread.pin', $thread, $includeCategory);
    }

    public static function canFeature(array $thread, bool $includeCategory = false): bool
    {
        return self::can('thread.feature', $thread, $includeCategory);
    }

    public static function canRecommend(array $thread, bool $includeCategory = false): bool
    {
        return self::can('thread.recommend', $thread, $includeCategory);
    }

    public static function canLock(array $thread, bool $includeCategory = false): bool
    {
        return self::can('thread.lock', $thread, $includeCategory);
    }

    public static function canChangeStatus(array $thread, string $status, bool $includeCategory = false): bool
    {
        if ($status === 'hidden' || $status === 'published') {
            return self::canHide($thread, $includeCategory);
        }
        if ($status === 'deleted') {
            return self::canDelete($thread, $includeCategory);
        }
        return false;
    }

    public static function canModerate(array $thread, string $action, bool $includeCategory = false): bool
    {
        return match ($action) {
            'top' => self::canPin($thread, $includeCategory),
            'featured' => self::canFeature($thread, $includeCategory),
            'recommended' => self::canRecommend($thread, $includeCategory),
            'locked' => self::canLock($thread, $includeCategory),
            default => false,
        };
    }

    public static function canAnyManage(array $thread, bool $includeCategory = false): bool
    {
        return self::canHide($thread, $includeCategory)
            || self::canDelete($thread, $includeCategory)
            || self::canPin($thread, $includeCategory)
            || self::canFeature($thread, $includeCategory)
            || self::canRecommend($thread, $includeCategory)
            || self::canLock($thread, $includeCategory);
    }
}
