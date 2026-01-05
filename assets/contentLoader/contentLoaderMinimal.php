<!-- Content Loader Minimal (tylko blob) -->
<!-- Użycie: $loaderTheme = 'dark'|'light', $loaderClass = 'custom-class' (opcjonalne) -->
<?php
$exampleLoaderTheme = isset($exampleLoaderTheme) ? $exampleLoaderTheme : 'dark';
$exampleLoaderClass = isset($exampleLoaderClass) ? $exampleLoaderClass : '';
?>
<style>
    .loaderOverlay {
        position: fixed;
        inset: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        pointer-events: none;
    }

    .loaderOverlay.hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .loader {
        position: relative;
        width: 180px;
        height: 120px;
        filter: url(#goo);
    }

    .loader span {
        position: absolute;
        border-radius: 50%;
        background: var(--accent-blue, #3C7EF5);
        will-change: transform;
    }

    .loader span:nth-child(1) {
        width: 64px;
        height: 64px;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        animation: lavaCenter 4s ease-in-out infinite;
    }

    .loader span:nth-child(2) {
        width: 42px;
        height: 42px;
        left: 50%;
        top: 50%;
        animation: lavaOrbit1 3.2s ease-in-out infinite;
    }

    .loader span:nth-child(3) {
        width: 32px;
        height: 32px;
        left: 50%;
        top: 50%;
        animation: lavaOrbit2 3.8s ease-in-out infinite;
    }

    .loader span:nth-child(4) {
        width: 24px;
        height: 24px;
        left: 50%;
        top: 50%;
        animation: lavaOrbit3 4.2s ease-in-out infinite;
    }

    @keyframes lavaCenter {

        0%,
        100% {
            transform: translate(-50%, -50%) scale(1);
        }

        25% {
            transform: translate(-50%, -50%) scale(1.08);
        }

        50% {
            transform: translate(-50%, -50%) scale(0.92);
        }

        75% {
            transform: translate(-50%, -50%) scale(1.05);
        }
    }

    @keyframes lavaOrbit1 {

        0%,
        100% {
            transform: translate(-50%, -50%);
        }

        15% {
            transform: translate(-50%, -50%) translate(48px, -12px);
        }

        30% {
            transform: translate(-50%, -50%) translate(20px, 8px);
        }

        50% {
            transform: translate(-50%, -50%) translate(-10px, 5px);
        }

        70% {
            transform: translate(-50%, -50%) translate(-48px, 12px);
        }

        85% {
            transform: translate(-50%, -50%) translate(-15px, -8px);
        }
    }

    @keyframes lavaOrbit2 {

        0%,
        100% {
            transform: translate(-50%, -50%);
        }

        20% {
            transform: translate(-50%, -50%) translate(-30px, -42px);
        }

        40% {
            transform: translate(-50%, -50%) translate(-8px, -5px);
        }

        60% {
            transform: translate(-50%, -50%) translate(30px, 42px);
        }

        80% {
            transform: translate(-50%, -50%) translate(8px, 5px);
        }
    }

    @keyframes lavaOrbit3 {

        0%,
        100% {
            transform: translate(-50%, -50%);
        }

        20% {
            transform: translate(-50%, -50%) translate(52px, 28px);
        }

        45% {
            transform: translate(-50%, -50%) translate(5px, -8px);
        }

        70% {
            transform: translate(-50%, -50%) translate(-52px, -28px);
        }

        90% {
            transform: translate(-50%, -50%) translate(-5px, 10px);
        }
    }

    /* Mobile styles */
    @media (max-width: 1200px) {

        html,
        body {
            overflow-x: hidden;
            max-width: 100vw;
        }

        .loaderOverlay {
            overflow: hidden;
            left: 0;
            right: 0;
            top: 0;
            bottom: 0;
        }

        .loader {
            width: 100px;
            height: 80px;
            overflow: hidden;
        }

        .loader span:nth-child(1) {
            width: 40px;
            height: 40px;
        }

        .loader span:nth-child(2) {
            width: 28px;
            height: 28px;
        }

        .loader span:nth-child(3) {
            width: 22px;
            height: 22px;
        }

        .loader span:nth-child(4) {
            width: 16px;
            height: 16px;
        }

        @keyframes lavaOrbit1 {

            0%,
            100% {
                transform: translate(-50%, -50%);
            }

            15% {
                transform: translate(-50%, -50%) translate(24px, -8px);
            }

            30% {
                transform: translate(-50%, -50%) translate(10px, 4px);
            }

            50% {
                transform: translate(-50%, -50%) translate(-6px, 3px);
            }

            70% {
                transform: translate(-50%, -50%) translate(-24px, 8px);
            }

            85% {
                transform: translate(-50%, -50%) translate(-8px, -4px);
            }
        }

        @keyframes lavaOrbit2 {

            0%,
            100% {
                transform: translate(-50%, -50%);
            }

            20% {
                transform: translate(-50%, -50%) translate(-16px, -20px);
            }

            40% {
                transform: translate(-50%, -50%) translate(-4px, -3px);
            }

            60% {
                transform: translate(-50%, -50%) translate(16px, 20px);
            }

            80% {
                transform: translate(-50%, -50%) translate(4px, 3px);
            }
        }

        @keyframes lavaOrbit3 {

            0%,
            100% {
                transform: translate(-50%, -50%);
            }

            20% {
                transform: translate(-50%, -50%) translate(26px, 14px);
            }

            45% {
                transform: translate(-50%, -50%) translate(3px, -4px);
            }

            70% {
                transform: translate(-50%, -50%) translate(-26px, -14px);
            }

            90% {
                transform: translate(-50%, -50%) translate(-3px, 6px);
            }
        }
    }
</style>
<svg style="position:absolute;width:0;height:0;">
    <defs>
        <filter id="goo">
            <feGaussianBlur in="SourceGraphic" stdDeviation="12" result="blur" />
            <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 28 -11"
                result="goo" />
            <feBlend in="SourceGraphic" in2="goo" />
        </filter>
    </defs>
</svg>
<div class="loaderOverlay <?php echo $exampleLoaderTheme . ($exampleLoaderClass ? ' ' . $exampleLoaderClass : ''); ?>"
    id="pageLoader">
    <div class="loader">
        <span></span><span></span><span></span><span></span>
    </div>
</div>