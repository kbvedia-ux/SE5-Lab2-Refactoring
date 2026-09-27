<?php
require_once 'config.php';

function adminBadge(string $text, string $type = 'orange'): string
{
    return '<span class="admin-badge badge-' . htmlspecialchars($type) . '">' . htmlspecialchars($text) . '</span>';
}

function adminScoreTone(int $score): string
{
    if ($score >= 80) {
        return 'green';
    }

    if ($score >= 60) {
        return 'orange';
    }

    return 'red';
}

function adminStart(string $title, string $subtitle, string $actionHtml = ''): void
{
    requireAdmin();
    $flash = pullFlash();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - UA Admin Panel</title>
        <link rel="stylesheet" href="css/style.css?v=49">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    </head>
    <body class="admin-page">
        <?php include 'admin_header.php'; ?>

        <main class="admin-main">
            <section class="admin-content">
                <?php if ($flash): ?>
                    <div class="portal-flash flash-<?php echo htmlspecialchars($flash['type']); ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>
                <div class="admin-page-heading">
                    <div>
                        <h1><?php echo htmlspecialchars($title); ?></h1>
                        <p><?php echo htmlspecialchars($subtitle); ?></p>
                    </div>
                    <?php echo $actionHtml; ?>
                </div>
    <?php
}

function adminEnd(): void
{
    ?>
            </section>
        </main>
        <script src="js/portal.js?v=11"></script>
    </body>
    </html>
    <?php
}
?>
