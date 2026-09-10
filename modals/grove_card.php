<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Narra Grove</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="grove_card.css?v=<?= filemtime(__DIR__ . '/grove_card.css') ?>">
</head>
<body>
    <main class="grove-modal" role="dialog" aria-modal="true" aria-labelledby="grove-modal-title">
        <header class="grove-modal__header">
            <div>
                <h1 id="grove-modal-title">Narra</h1>
                <p>Pterocarpus indicus <span aria-hidden="true">·</span> Native</p>
            </div>
            <span class="grove-modal__day">Day 4 of 5</span>
            <button type="button" class="grove-modal__close" onclick="parent.hideFloating()" aria-label="Close grove details">&times;</button>
        </header>

        <section class="grove-modal__content" aria-label="Narra tending progress">
            <button type="button" class="grove-tree-action" aria-disabled="true" data-tooltip="Click me!" aria-label="Narra tree; watering is not available in this preview">
                <svg class="grove-tree-illustration" viewBox="0 0 320 320" aria-hidden="true" focusable="false">
                    <g class="grove-tree-illustration__soil">
                        <ellipse cx="160" cy="276" rx="120" ry="10" fill="oklch(0.78 0.04 90)" />
                        <ellipse cx="160" cy="272" rx="80" ry="6" fill="oklch(0.55 0.05 80)" opacity=".5" />
                    </g>
                    <g class="grove-tree-illustration__trunk">
                        <rect x="152.6" y="168" width="14.8" height="108" rx="7.4" fill="oklch(0.34 0.04 50)" />
                        <path d="M145.2 276q-10-2-16 2m29.6-2q10-2 16 2" fill="none" stroke="oklch(0.30 0.04 50)" stroke-linecap="round" stroke-width="3" />
                    </g>
                    <g class="grove-tree-illustration__canopy">
                        <circle cx="160" cy="115" r="74" fill="oklch(0.45 0.09 150)" />
                        <circle cx="115.6" cy="129.8" r="51.8" fill="oklch(0.5 0.1 148)" />
                        <circle cx="204.4" cy="129.8" r="51.8" fill="oklch(0.55 0.1 145)" />
                        <circle cx="160" cy="78" r="40.7" fill="oklch(0.6 0.11 142)" />
                    </g>
                </svg>
            </button>

            <div class="grove-progress" aria-label="Day 4 of 5 growth progress">
                <div class="grove-progress__track"><span style="width: 57.1429%"></span></div>
                <strong>Day 4 of 7</strong>
                <p>Tree already watered! Please wait 18 hours.</p>
            </div>
        </section>

        <footer class="grove-modal__footer">
            <p class="fun-fact-note">Narra trees can live for more than 100 years and are considered a symbol of strength and resilience.</p>
        </footer>
    </main>

    <script>
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') parent.hideFloating();
        });
    </script>
</body>
</html>
