<template>
    <div class="absolute inset-0 -z-10 overflow-hidden">
        <div class="mesh-bg"></div>

        <!-- Soft organic blob 1 -->
        <div class="blob blob1"></div>

        <!-- Soft organic blob 2 -->
        <div class="blob blob2"></div>

        <!-- Soft organic blob 3 -->
        <div class="blob blob3"></div>
        <!-- Organic curved contour 1 -->
        <svg class="contour contour1" viewBox="0 0 800 800">
            <path d="M400,80 
                    C550,120 720,250 680,400 
                    C640,550 500,700 350,680 
                    C200,660 120,520 130,380 
                    C140,240 250,100 400,80Z" />
        </svg>

        <!-- Organic curved contour 2 -->
        <svg class="contour contour2" viewBox="0 0 800 800">
            <path d="M400,50 
                    C600,100 760,260 720,430 
                    C680,600 520,750 340,720 
                    C160,690 80,530 120,360 
                    C160,190 240,100 400,50Z" />
        </svg>

        <!-- Organic curved contour 3 -->
        <svg class="contour contour3" viewBox="0 0 800 800">
            <path d="M400,110 
                    C520,130 650,240 620,360 
                    C590,480 480,610 350,600 
                    C220,590 160,460 180,350 
                    C200,240 260,140 400,110Z" />
        </svg>
    </div>
</template>

<style>

/* Base animated gradient */
.mesh-bg {
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg, #62a8ff, #b67bff, #ff7ad2);
    background-size: 200% 200%;
    animation: meshMove 12s ease-in-out infinite;
    filter: blur(40px);
}

/* Blobs */
.blob {
    position: absolute;
    border-radius: 40%;
    filter: blur(60px);
    opacity: 0.45;
    mix-blend-mode: screen;
}

/* Blob colors + positions */
.blob1 {
    width: 60vw;
    height: 60vw;
    background: #7cb8ff;
    top: -10%;
    left: -20%;
    animation: blobFloat1 18s infinite ease-in-out alternate;
}

.blob2 {
    width: 55vw;
    height: 55vw;
    background: #c47cff;
    bottom: -15%;
    right: -10%;
    animation: blobFloat2 22s infinite ease-in-out alternate;
}

.blob3 {
    width: 50vw;
    height: 50vw;
    background: #ff66c4;
    top: 40%;
    left: 35%;
    animation: blobFloat3 20s infinite ease-in-out alternate;
}

/* Animations */
@keyframes meshMove {
    0% { background-position: 0% 50%; }
    100% { background-position: 100% 50%; }
}

@keyframes blobFloat1 {
    0% { transform: translate(0, 0) scale(1.1); }
    100% { transform: translate(40px, 30px) scale(1.2); }
}

@keyframes blobFloat2 {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(-40px, -20px) scale(1.15); }
}

@keyframes blobFloat3 {
    0% { transform: translate(0, 0) scale(1.05); }
    100% { transform: translate(25px, -25px) scale(1.15); }
}

/* Soft contour lines */
.contour {
    position: absolute;
    stroke: rgba(255, 255, 255, 0.12);
    fill: none;
    stroke-width: 2;
    filter: blur(2px);
    transform-origin: center;
    animation: contourRotate 22s infinite linear;
}

/* Position them */
/* Soft contour lines */
.contour {
    position: absolute;
    stroke: rgba(255, 255, 255, 0.22); /* Increased visibility */
    fill: none;
    stroke-width: 3; /* thicker lines */
    filter: blur(1px); /* less blur = sharper */
    transform-origin: center;
    animation: contourRotate 28s infinite linear;
}

/* Position & adjust opacity of each layer */
.contour1 {
    width: 1200px;
    height: 1200px;
    top: -180px;
    left: -240px;
    stroke-opacity: 0.25; /* stronger */
}

.contour2 {
    width: 1500px;
    height: 1500px;
    top: -260px;
    right: -260px;
    stroke-opacity: 0.18;
}

.contour3 {
    width: 1700px;
    height: 1700px;
    bottom: -280px;
    left: -180px;
    stroke-opacity: 0.15;
}


/* Animation */
@keyframes contourRotate {
    0%   { transform: rotate(0deg) scale(1); }
    50%  { transform: rotate(180deg) scale(1.05); }
    100% { transform: rotate(360deg) scale(1); }
}

</style>
