<?php
require_once 'config.php';

function badge(string $text, string $type = 'green'): string
{
    return '<span class="portal-badge badge-' . $type . '">' . htmlspecialchars($text) . '</span>';
}

function filterBar(array $houses = [], array $statuses = []): void
{
    ?>
    <div class="filter-bar">
        <label class="portal-search wide"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search..."></label>
        <?php if ($houses): ?>
            <span class="filter-label">House</span>
            <button class="filter-chip active">All</button>
            <?php foreach ($houses as $house): ?><button class="filter-chip"><?php echo htmlspecialchars($house); ?></button><?php endforeach; ?>
        <?php endif; ?>
        <?php if ($statuses): ?>
            <span class="filter-label">Status</span>
            <?php foreach ($statuses as $index => $status): ?><button class="filter-chip <?php echo $index === 0 ? 'active' : ''; ?>"><?php echo htmlspecialchars($status); ?></button><?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
}

function portalStart(string $title, string $subtitle, string $actionHtml = ''): void
{
    requireLandlord();
    $flash = pullFlash();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - BH Accreditation</title>
        <link rel="stylesheet" href="css/style.css?v=51">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    </head>
    <body class="portal-page">
        <main class="main-content">
            <?php include 'header.php'; ?>

            <section class="portal-content">
                <?php if ($flash): ?>
                    <div class="portal-flash flash-<?php echo htmlspecialchars($flash['type']); ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>
                <div class="page-heading">
                    <div>
                        <h1><?php echo htmlspecialchars($title); ?></h1>
                        <p><?php echo htmlspecialchars($subtitle); ?></p>
                    </div>
                    <?php echo $actionHtml; ?>
                </div>
    <?php
}

function portalEnd(): void
{
    ?>
            </section>
        </main>
        <script src="js/portal.js?v=14"></script>
    </body>
    </html>
    <?php
}
?>
