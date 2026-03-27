<?php
/**
 * Sivis Drive - Maintenance Mode Page
 */
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sivis Drive - Przerwa Techniczna</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 flex items-center justify-center min-h-screen p-4 overflow-hidden">
    <!-- Animated background -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600/10 blur-[120px] rounded-full animate-pulse"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-indigo-600/10 blur-[120px] rounded-full animate-pulse" style="animation-delay: 2s"></div>
    </div>

    <div class="relative z-10 w-full max-w-md text-center">
        <div class="mb-8 inline-flex p-5 rounded-3xl bg-slate-900 border border-slate-800 shadow-2xl relative overflow-hidden group">
            <div class="absolute inset-0 bg-blue-500/5 group-hover:bg-blue-500/10 transition-colors"></div>
            <i data-lucide="shield-alert" class="w-16 h-16 text-blue-400 relative z-10"></i>
        </div>

        <h1 class="text-3xl font-bold mb-4 bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">
            Przerwa Techniczna
        </h1>
        
        <p class="text-slate-400 text-lg mb-8 leading-relaxed">
            Aktualnie wykonujemy <span class="text-blue-400 font-semibold">automatyczną kopię zapasową</span> Twoich danych. System zostanie przywrócony w ciągu kilku minut.
        </p>

        <div class="bg-slate-900/50 backdrop-blur-xl border border-white/5 rounded-2xl p-6 mb-8 text-left">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-2 h-2 rounded-full bg-blue-500 animate-ping"></div>
                <span class="text-sm font-medium text-slate-300">Zabezpieczanie plików...</span>
            </div>
            <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                <div class="h-full bg-blue-500 animate-[loading_3s_ease-in-out_infinite] shadow-[0_0_10px_rgba(59,130,246,0.5)]"></div>
            </div>
        </div>

        <p class="text-xs text-slate-500 uppercase tracking-widest font-bold opacity-50">
            Sivis Drive &bull; Bezpieczeństwo danych
        </p>
    </div>

    <script>
        lucide.createIcons();
    </script>
    <style>
        @keyframes loading {
            0% { width: 0%; transform: translateX(-100%); }
            50% { width: 100%; transform: translateX(0); }
            100% { width: 0%; transform: translateX(100%); }
        }
    </style>
</body>
</html>
