<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class BannerModel
{
    public function active(string $placement = 'home'): array
    {
        $placement = in_array($placement, ['home', 'section'], true) ? $placement : 'home';
        $stmt = Database::connection()->prepare(
            "SELECT b.id, b.title, b.description, b.image, b.url, b.thread_id, b.placement, t.section_id, s.name AS section_name
             FROM banners b
             LEFT JOIN threads t ON t.id = b.thread_id
             LEFT JOIN sections s ON s.id = t.section_id
             WHERE b.status = 'active' AND b.placement = :placement
             ORDER BY b.sort_order ASC, b.id ASC
             LIMIT 8"
        );
        $stmt->execute([':placement' => $placement]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sectionBroadcasts(int $limit = 8): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT b.id, b.title, b.description,
                    COALESCE(
                        t.cover,
                        (SELECT a.path FROM attachments a WHERE a.thread_id = t.id AND a.kind = 'image' ORDER BY a.id ASC LIMIT 1),
                        CASE WHEN t.content LIKE '%<img%src=%'
                             THEN SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(t.content, 'src=\"', -1), '\"', 1), '?', 1)
                             ELSE NULL END,
                        b.image
                    ) AS image,
                    b.url, b.thread_id, t.section_id, s.name AS section_name
             FROM banners b
             INNER JOIN threads t ON t.id = b.thread_id AND t.status = 'published'
             LEFT JOIN sections s ON s.id = t.section_id
             WHERE b.status = 'active' AND b.placement = 'section'
             ORDER BY b.sort_order ASC, b.id ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
