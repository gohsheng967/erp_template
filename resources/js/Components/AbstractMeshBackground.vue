<template>
    <div class="auth-bg" aria-hidden="true">
        <div class="auth-bg__base"></div>
        <div class="auth-bg__orb auth-bg__orb--blue"></div>
        <div class="auth-bg__orb auth-bg__orb--teal"></div>
        <div class="auth-bg__orb auth-bg__orb--amber"></div>
        <div class="auth-bg__grid"></div>
        <div class="auth-bg__noise"></div>
    </div>
</template>

<style scoped>
.auth-bg {
    --bg-top: #f6fbff;
    --bg-bottom: #dce9ff;
    --orb-blue: rgba(32, 122, 255, 0.3);
    --orb-teal: rgba(19, 184, 166, 0.25);
    --orb-amber: rgba(245, 158, 11, 0.18);
    position: absolute;
    inset: 0;
    overflow: hidden;
    z-index: -10;
    pointer-events: none;
}

.auth-bg__base {
    position: absolute;
    inset: 0;
    background: radial-gradient(130% 120% at 0% 100%, #e8f2ff 0%, rgba(232, 242, 255, 0) 55%),
        radial-gradient(120% 110% at 100% 0%, #eefaf8 0%, rgba(238, 250, 248, 0) 50%),
        linear-gradient(160deg, var(--bg-top), var(--bg-bottom));
}

.auth-bg__orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(50px);
    mix-blend-mode: multiply;
    animation: float 18s ease-in-out infinite alternate;
}

.auth-bg__orb--blue {
    width: min(52vw, 560px);
    height: min(52vw, 560px);
    top: -14%;
    left: -10%;
    background: var(--orb-blue);
}

.auth-bg__orb--teal {
    width: min(42vw, 480px);
    height: min(42vw, 480px);
    right: -8%;
    top: 30%;
    background: var(--orb-teal);
    animation-duration: 21s;
}

.auth-bg__orb--amber {
    width: min(38vw, 420px);
    height: min(38vw, 420px);
    bottom: -12%;
    left: 30%;
    background: var(--orb-amber);
    animation-duration: 24s;
}

.auth-bg__grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.32) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.32) 1px, transparent 1px);
    background-size: 34px 34px;
    mask-image: radial-gradient(circle at center, black 42%, transparent 88%);
}

.auth-bg__noise {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(13, 38, 76, 0.06) 0.65px, transparent 0.65px);
    background-size: 3px 3px;
    opacity: 0.35;
}

@keyframes float {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    100% {
        transform: translate3d(38px, -28px, 0) scale(1.08);
    }
}

@media (max-width: 640px) {
    .auth-bg__grid {
        background-size: 24px 24px;
    }

    .auth-bg__orb {
        filter: blur(38px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .auth-bg__orb {
        animation: none;
    }
}
</style>
