<?php
// ==========================================
// 1. API ROUTER & FAST METADATA EXTRACTOR
// ==========================================
$musicDir = __DIR__ . '/music';
$cacheFile = __DIR__ . '/music_cache.json';

function extractMetadata($filePath, $fileUrl, $folderName) {
    $meta = ['title' => pathinfo($filePath, PATHINFO_FILENAME), 'artist' => 'Unknown Artist', 'cover' => null, 'url' => $fileUrl, 'folder' => $folderName, 'mtime' => filemtime($filePath)];
    $fp = @fopen($filePath, 'rb');
    if ($fp) {
        $data = fread($fp, 2 * 1024 * 1024);
        fclose($fp);
        
        // Safely extract Title (TIT2) by calculating actual frame size
        if (($pos = strpos($data, 'TIT2')) !== false) {
            $size = unpack('N', substr($data, $pos + 4, 4))[1];
            if ($size > 0 && $size < 1000) {
                $text = substr($data, $pos + 10, $size);
                $clean = trim(preg_replace('/[^\x20-\x7E]/', '', str_replace("\x00", '', $text)));
                if (!empty($clean)) $meta['title'] = $clean;
            }
        }
        
        // Safely extract Artist (TPE1) by calculating actual frame size
        if (($pos = strpos($data, 'TPE1')) !== false) {
            $size = unpack('N', substr($data, $pos + 4, 4))[1];
            if ($size > 0 && $size < 1000) {
                $text = substr($data, $pos + 10, $size);
                $clean = trim(preg_replace('/[^\x20-\x7E]/', '', str_replace("\x00", '', $text)));
                if (!empty($clean)) $meta['artist'] = $clean;
            }
        }

        $jpgStart = strpos($data, "\xFF\xD8\xFF");
        if ($jpgStart !== false) {
            $jpgEnd = strpos($data, "\xFF\xD9", $jpgStart);
            if ($jpgEnd !== false) $meta['cover'] = 'data:image/jpeg;base64,' . base64_encode(substr($data, $jpgStart, $jpgEnd - $jpgStart + 2));
        } elseif (($pngStart = strpos($data, "\x89PNG\r\n\x1a\n")) !== false) {
            $pngEnd = strpos($data, "IEND", $pngStart);
            if ($pngEnd !== false) $meta['cover'] = 'data:image/png;base64,' . base64_encode(substr($data, $pngStart, $pngEnd - $pngStart + 8));
        }
    }
    return $meta;
}

// Handle Background AJAX Requests
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $cache = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
    $cacheUpdated = false;
    $tracks = [];

    if ($_GET['api'] === 'get_random') {
        $allFiles = [];
        if (is_dir($musicDir)) {
            foreach (scandir($musicDir) as $item) {
                if ($item === '.' || $item === '..' || $item === 'covers') continue;
                $path = $musicDir . '/' . $item;
                if (is_dir($path)) {
                    foreach (scandir($path) as $subItem) {
                        if (in_array(strtolower(pathinfo($subItem, PATHINFO_EXTENSION)), ['mp3', 'm4a'])) {
                            $allFiles[] = ['path' => $path . '/' . $subItem, 'folder' => $item, 'filename' => $subItem];
                        }
                    }
                } elseif (in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), ['mp3', 'm4a'])) {
                    $allFiles[] = ['path' => $path, 'folder' => 'All Tracks', 'filename' => $item];
                }
            }
        }
        shuffle($allFiles);
        $selectedFiles = array_slice($allFiles, 0, 5); // Pick 5 random songs

        foreach ($selectedFiles as $file) {
            $fileUrl = $file['folder'] === 'All Tracks' ? 'music/' . rawurlencode($file['filename']) : 'music/' . rawurlencode($file['folder']) . '/' . rawurlencode($file['filename']);
            $mtime = filemtime($file['path']);
            if (!isset($cache[$fileUrl]) || $cache[$fileUrl]['mtime'] !== $mtime) {
                $cache[$fileUrl] = extractMetadata($file['path'], $fileUrl, $file['folder']);
                $cacheUpdated = true;
            }
            $tracks[] = $cache[$fileUrl];
        }
    } elseif ($_GET['api'] === 'get_playlist' && isset($_GET['folder'])) {
        $folder = $_GET['folder'];
        $targetDir = $folder === 'All Tracks' ? $musicDir : $musicDir . '/' . $folder;
        if (is_dir($targetDir)) {
            foreach (scandir($targetDir) as $item) {
                if (is_file($targetDir . '/' . $item) && in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), ['mp3', 'm4a'])) {
                    $filePath = $targetDir . '/' . $item;
                    $fileUrl = $folder === 'All Tracks' ? 'music/' . rawurlencode($item) : 'music/' . rawurlencode($folder) . '/' . rawurlencode($item);
                    $mtime = filemtime($filePath);
                    if (!isset($cache[$fileUrl]) || $cache[$fileUrl]['mtime'] !== $mtime) {
                        $cache[$fileUrl] = extractMetadata($filePath, $fileUrl, $folder);
                        $cacheUpdated = true;
                    }
                    $tracks[] = $cache[$fileUrl];
                }
            }
        }
    }

    if ($cacheUpdated) file_put_contents($cacheFile, json_encode($cache));
    echo json_encode($tracks);
    exit;
}

// Normal Page Load: Just scan for folder names
$playlists = ['Home', 'All Tracks'];
if (is_dir($musicDir)) {
    foreach (scandir($musicDir) as $item) {
        if ($item !== '.' && $item !== '..' && $item !== 'covers' && is_dir($musicDir . '/' . $item)) {
            $playlists[] = $item;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Royal's Music Player</title>
    <!--  favicon links here -->
    <link rel="icon" type="image/png" href="Icons/Favicon.png">
    <link rel="apple-touch-icon" href="Icons/Favicon.png">
    
    <style>
        :root { --bg: #121212; --panel: #181818; --hover: #2a2a2a; --primary: #1DB954; --text: #fff; --sub: #b3b3b3; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: var(--bg); color: var(--text); padding-bottom: 120px; }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 24px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; position: sticky; top: 0; background: var(--bg); padding: 10px 0; z-index: 10; }
        .search-bar { background: #242424; color: #fff; border: none; padding: 12px 20px; border-radius: 50px; width: 100%; max-width: 350px; outline: none; }
        
        .playlist-tabs { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 15px; margin-bottom: 20px; scrollbar-width: none; }
        .playlist-tabs::-webkit-scrollbar { display: none; }
        .pl-tab { background: #242424; color: var(--text); border: none; padding: 10px 18px; border-radius: 20px; font-size: 14px; cursor: pointer; white-space: nowrap; transition: 0.2s; }
        .pl-tab:hover { background: #333; }
        .pl-tab.active { background: var(--primary); color: #000; font-weight: bold; }

        .track-row { display: grid; grid-template-columns: 40px 1fr; align-items: center; padding: 10px 16px; border-radius: 6px; cursor: pointer; transition: 0.2s; }
        .track-row:hover, .track-row.active { background: var(--hover); }
        .track-row.active .t-title { color: var(--primary); font-weight: bold; }
        
        .t-num { color: var(--sub); font-size: 14px; text-align: center; }
        .t-info { display: flex; align-items: center; gap: 16px; overflow: hidden; }
        .t-img { width: 44px; height: 44px; border-radius: 4px; background: #282828; object-fit: cover; flex-shrink: 0; }
        .t-meta { overflow: hidden; white-space: nowrap; }
        .t-title { font-size: 15px; text-overflow: ellipsis; overflow: hidden; }
        .t-artist { font-size: 13px; color: var(--sub); margin-top: 4px; text-overflow: ellipsis; overflow: hidden; }

        .player-bar { background: var(--panel); border-top: 1px solid #282828; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; position: fixed; bottom: 0; width: 100%; z-index: 50; }
        /* Added cursor pointer to trigger full screen */
        .now-playing { display: flex; align-items: center; gap: 14px; width: 30%; min-width: 180px; cursor: pointer; transition: 0.2s; }
        .now-playing:hover { opacity: 0.8; }
        
        .now-img { width: 56px; height: 56px; border-radius: 4px; background: #282828; object-fit: cover; }
        .now-title { font-size: 14px; font-weight: 600; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;}
        .now-artist { font-size: 12px; color: var(--sub); margin-top: 4px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;}

        .controls-wrapper { display: flex; flex-direction: column; align-items: center; width: 40%; max-width: 500px; }
        .controls { display: flex; align-items: center; gap: 20px; margin-bottom: 8px; }
        .btn { background: none; border: none; color: var(--sub); font-size: 18px; cursor: pointer; transition: 0.2s; }
        .btn:hover { color: var(--text); transform: scale(1.05); }
        .btn-play { background: #fff; color: #000; width: 36px; height: 36px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 16px; }

        .progress { display: flex; align-items: center; gap: 8px; width: 100%; font-size: 11px; color: var(--sub); }
        .bar { flex: 1; height: 4px; background: #4d4d4d; border-radius: 2px; cursor: pointer; position: relative; }
        .bar-fill { height: 100%; background: var(--text); border-radius: 2px; width: 0%; }
        .bar:hover .bar-fill { background: var(--primary); }
        
        .extra-controls { width: 30%; display: flex; justify-content: flex-end; align-items: center; gap: 10px; }
        .volume-bar { width: 80px; height: 4px; background: #4d4d4d; border-radius: 2px; cursor: pointer; }
        .volume-fill { height: 100%; background: var(--text); border-radius: 2px; width: 100%; }

        .tooltip-btn { position: relative; }
        .tooltip-btn::after {
            content: attr(data-tooltip); position: absolute; bottom: 140%; left: 50%;
            transform: translateX(-50%) translateY(10px); background: #282828; color: #fff;
            padding: 6px 10px; border-radius: 4px; font-size: 12px; white-space: nowrap;
            opacity: 0; visibility: hidden; pointer-events: none; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 100;
        }
        .tooltip-btn:hover::after { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }
        
        .loader { text-align: center; padding: 40px; color: var(--sub); font-size: 14px; }

        /* =========================================
           FULLSCREEN OVERLAY STYLES
           ========================================= */
        .fs-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(180deg, #2b2b2b 0%, #121212 100%);
            z-index: 1000; display: flex; flex-direction: column; align-items: center;
            padding: 20px; transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        }
        .fs-overlay.active { transform: translateY(0); }
        .fs-header { width: 100%; max-width: 500px; display: flex; justify-content: flex-start; margin-bottom: 30px; margin-top: 10px; }
        
        .fs-art-container { width: 100%; max-width: 350px; aspect-ratio: 1; border-radius: 12px; overflow: hidden; margin-bottom: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
        .fs-art-container img { width: 100%; height: 100%; object-fit: cover; }
        
        /* Marquee Scrolling Text */
        .fs-marquee-container { width: 100%; max-width: 350px; overflow: hidden; white-space: nowrap; margin-bottom: 30px; position: relative; }
        .fs-marquee-content { display: inline-block; padding-left: 100%; animation: marquee 12s linear infinite; }
        .fs-title { font-size: 24px; font-weight: bold; margin-right: 12px; color: var(--text); }
        .fs-artist { font-size: 18px; color: var(--sub); }
        
        @keyframes marquee {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-100%, 0); }
        }

        .fs-controls-wrapper { width: 100%; max-width: 400px; display: flex; flex-direction: column; gap: 25px; }
        .fs-controls { display: flex; align-items: center; justify-content: space-between; width: 100%; }
        .fs-controls .btn { font-size: 22px; }
        .fs-controls .btn-play { width: 64px; height: 64px; font-size: 24px; }
        
        /* Credit Badge Base - Hidden on Desktop */
        .credit-badge { display: none; }
        /* ========================================= */

        @media (max-width: 768px) {
            header { flex-direction: column; align-items: flex-start; gap: 16px; }
            .search-bar { max-width: 100%; }
            .extra-controls { display: none; }
            .now-playing { width: auto; max-width: 45%; min-width: 0; }
            .now-img { width: 44px; height: 44px; }
            .controls-wrapper { width: 55%; max-width: none; }
            .controls { gap: 12px; }
            
            /* Hides everything except play/pause in the bottom bar on mobile */
            .hide-on-mobile { display: none !important; }
            
            .btn { font-size: 16px; }
            .btn-play { width: 32px; height: 32px; font-size: 14px; }
            .player-bar { padding: 12px 16px; }
            .track-row { grid-template-columns: 30px 1fr; padding: 10px 8px; }
            .container { padding: 16px; }
            .tooltip-btn::after { display: none !important; }
            
            /* Mobile adjustments for Fullscreen */
            .fs-art-container { max-width: 80vw; }
            .fs-marquee-container { max-width: 80vw; }
            
            /* Mobile Credit Badge */
            .credit-badge {
                display: inline-block;
                margin-top: 40px;
                background: var(--primary);
                color: #fff;
                padding: 10px 24px;
                border-radius: 50px;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: 0.5px;
                text-shadow: 0 1px 2px rgba(0,0,0,0.2);
                animation: breathe 3.5s ease-in-out infinite;
                transform-origin: center;
            }
            
            @keyframes breathe {
                0%, 100% { 
                    transform: scale(1); 
                    box-shadow: 0 0 10px rgba(255, 255, 255, 0.15), 0 4px 10px rgba(0,0,0,0.3); 
                }
                50% { 
                    transform: scale(1.05); 
                    box-shadow: 0 0 22px rgba(255, 255, 255, 0.35), 0 6px 15px rgba(0,0,0,0.4); 
                }
            }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1 style="font-size: 28px;">Royal's Music Player</h1>
        <input type="text" class="search-bar" id="search" placeholder="Search tracks..." oninput="filterSearch()">
    </header>

    <div class="playlist-tabs" id="playlist-tabs">
        <?php foreach ($playlists as $pl): ?>
            <button class="pl-tab <?= $pl === 'Home' ? 'active' : '' ?>" onclick="loadPlaylist('<?= addslashes(htmlspecialchars($pl)) ?>')"><?= htmlspecialchars($pl) ?></button>
        <?php endforeach; ?>
    </div>

    <div id="tracklist">
        <div class="loader">Loading tracks...</div>
    </div>
</div>

<div class="player-bar">
    <div class="now-playing" onclick="toggleFullScreen()">
        <img id="now-img" class="now-img" style="display:none;">
        <div id="now-placeholder" class="now-img" style="display:flex;align-items:center;justify-content:center;font-size:24px;">🎵</div>
        <div style="overflow: hidden;">
            <div class="now-title" id="now-title">Select a track</div>
            <div class="now-artist" id="now-artist">---</div>
        </div>
    </div>

    <div class="controls-wrapper">
        <div class="controls">
           <!-- Added hide-on-mobile class to hide extra controls on smaller screens -->
           <button class="btn tooltip-btn hide-on-mobile" data-tooltip="Enable shuffle" id="btn-shuffle" onclick="toggleShuffle()">🔀</button>
            <button class="btn tooltip-btn" data-tooltip="Previous" onclick="prevTrack()">⏮</button>
            <button class="btn btn-play tooltip-btn" data-tooltip="Play" id="btn-play" onclick="togglePlay()">▶</button>
            <button class="btn tooltip-btn" data-tooltip="Next" onclick="nextTrack()">⏭</button>
            <button class="btn tooltip-btn hide-on-mobile" data-tooltip="Enable repeat" id="btn-repeat" onclick="toggleRepeat()">🔁</button>
        </div>
        <div class="progress hide-on-mobile"> <!-- Hides the progress bar on mobile bottom bar -->
            <span id="time-curr">0:00</span>
            <div class="bar" id="progress-bar" onclick="seek(event)">
                <div class="bar-fill" id="progress-fill"></div>
            </div>
            <span id="time-dur">0:00</span>
        </div>
    </div>
    
    <div class="extra-controls">
        <span style="color:var(--sub); font-size: 14px;">🔊</span>
        <div class="volume-bar" id="volume-bar" onclick="setVolume(event)">
            <div class="volume-fill" id="volume-fill"></div>
        </div>
    </div>
</div>

<!-- FULLSCREEN OVERLAY -->
<div class="fs-overlay" id="fs-player">
    <div class="fs-header">
        <button class="btn" onclick="toggleFullScreen()" style="font-size: 36px;">⌄</button>
    </div>
    
    <div class="fs-art-container">
        <img id="fs-img" src="" style="display:none;">
        <div id="fs-placeholder" style="display:flex;align-items:center;justify-content:center;font-size:80px;background:#282828;width:100%;height:100%;">🎵</div>
    </div>
    
    <div class="fs-marquee-container">
        <div class="fs-marquee-content">
            <span class="fs-title" id="fs-title">Select a track</span>
            <span class="fs-artist" id="fs-artist">---</span>
        </div>
    </div>
    
    <div class="fs-controls-wrapper">
        <div class="progress" style="margin-bottom: 10px;">
            <span id="fs-time-curr">0:00</span>
            <div class="bar" id="fs-progress-bar" onclick="seek(event)">
                <div class="bar-fill" id="fs-progress-fill"></div>
            </div>
            <span id="fs-time-dur">0:00</span>
        </div>
        
        <div class="fs-controls">
            <button class="btn" id="fs-btn-shuffle" onclick="toggleShuffle()">🔀</button>
            <button class="btn" onclick="prevTrack()">⏮</button>
            
            <!-- 15s Skip Backward -->
            <button class="btn" onclick="skipTime(-15)" style="font-size: 16px; font-weight: 600;">-15s</button>
            
            <button class="btn btn-play" id="fs-btn-play" onclick="togglePlay()">▶</button>
            
            <!-- 15s Skip Forward -->
            <button class="btn" onclick="skipTime(15)" style="font-size: 16px; font-weight: 600;">+15s</button>
            
            <button class="btn" onclick="nextTrack()">⏭</button>
            <button class="btn" id="fs-btn-repeat" onclick="toggleRepeat()">🔁</button>
        </div>
    </div>
    
    <!-- NEW: Mobile-only breathing credit badge -->
    <div class="credit-badge">Thoughtfully Curated With 💖 By Royal</div>
</div>

<audio id="audio" preload="auto" playsinline type="audio/mpeg"></audio>
<audio id="audio-preload" preload="auto" muted playsinline type="audio/mpeg"></audio>
  
<script>
    let playlistCache = {}; // Client-side cache to store fetched playlists
    let viewingTracks = []; 
    let playingQueue = []; 
    let activeFolder = 'Home';
    let currentIndex = -1;
    let nextIndex = -1;
    let isShuffle = false;
    let isRepeat = false;
    
    const audio = document.getElementById('audio');
    const audioPreload = document.getElementById('audio-preload');
    
    // UI Elements Array to easily update both normal and fullscreen buttons
    const playBtns = [document.getElementById('btn-play'), document.getElementById('fs-btn-play')];
    const shuffleBtns = [document.getElementById('btn-shuffle'), document.getElementById('fs-btn-shuffle')];
    const repeatBtns = [document.getElementById('btn-repeat'), document.getElementById('fs-btn-repeat')];
    
    // Load Home on Start
    window.onload = () => loadPlaylist('Home');

    function toggleFullScreen() {
        document.getElementById('fs-player').classList.toggle('active');
    }

    // 15 second skip function
    function skipTime(seconds) {
        if (!audio.duration) return;
        audio.currentTime = Math.max(0, Math.min(audio.currentTime + seconds, audio.duration));
    }

    function loadPlaylist(folderName) {
        activeFolder = folderName;
        document.querySelectorAll('.pl-tab').forEach(tab => {
            tab.classList.toggle('active', tab.textContent === folderName);
        });
        
        // If we already fetched this folder's data, load it instantly from memory
        if (playlistCache[folderName]) {
            viewingTracks = playlistCache[folderName];
            renderTracklist();
            return;
        }

        const container = document.getElementById('tracklist');
        container.innerHTML = '<div class="loader">Loading tracks...</div>';
        
        const endpoint = folderName === 'Home' ? '?api=get_random' : `?api=get_playlist&folder=${encodeURIComponent(folderName)}`;
        
        fetch(endpoint)
            .then(res => res.json())
            .then(data => {
                playlistCache[folderName] = data; // Save to cache for instant loading next time
                viewingTracks = data;
                renderTracklist();
            });
    }

    function renderTracklist(filterText = '') {
        const container = document.getElementById('tracklist');
        container.innerHTML = '';
        
        if (viewingTracks.length === 0) {
            container.innerHTML = '<div class="loader">No tracks found.</div>';
            return;
        }

        viewingTracks.forEach((track, index) => {
            if (filterText && !track.title.toLowerCase().includes(filterText) && !track.artist.toLowerCase().includes(filterText)) return;
            
            // Check if this specific track is currently playing
            const isCurrentlyPlaying = (playingQueue.length > 0 && playingQueue[currentIndex] && playingQueue[currentIndex].url === track.url);
            
            const coverHtml = track.cover 
                ? `<img src="${track.cover}" class="t-img" loading="lazy">` 
                : `<div class="t-img" style="display:flex;align-items:center;justify-content:center;font-size:18px;">🎵</div>`;

            const row = document.createElement('div');
            row.className = `track-row ${isCurrentlyPlaying ? 'active' : ''}`;
            row.onclick = () => playTrack(index);
            row.innerHTML = `
                <div class="t-num">${isCurrentlyPlaying ? '<span style="color:var(--primary)">▶</span>' : index + 1}</div>
                <div class="t-info">
                    ${coverHtml}
                    <div class="t-meta">
                        <div class="t-title">${track.title}</div>
                        <div class="t-artist">${track.artist} ${activeFolder === 'Home' ? ` • ${track.folder}` : ''}</div>
                    </div>
                </div>
            `;
            container.appendChild(row);
        });
    }

    function filterSearch() {
        renderTracklist(document.getElementById('search').value.toLowerCase());
    }

    function calculateAndPreloadNext() {
        if (playingQueue.length === 0) return;

        if (isRepeat) {
            nextIndex = currentIndex; 
        } else if (isShuffle) {
            if (playingQueue.length > 1) {
                do { nextIndex = Math.floor(Math.random() * playingQueue.length); } 
                while (nextIndex === currentIndex);
            } else {
                nextIndex = 0;
            }
        } else {
            nextIndex = (currentIndex < playingQueue.length - 1) ? currentIndex + 1 : 0;
        }

        if (nextIndex !== -1 && playingQueue[nextIndex]) {
            audioPreload.src = playingQueue[nextIndex].url;
            audioPreload.load();
        }
    }

    function playTrack(indexInView) {
        // Snapshot the current view into the active playing queue
        playingQueue = [...viewingTracks];
        currentIndex = indexInView;
        const track = playingQueue[currentIndex];

        audio.src = track.url;
        audio.play();
        
        playBtns.forEach(btn => {
            btn.textContent = '⏸';
            btn.setAttribute('data-tooltip', 'Pause');
        });

        // Update Bottom Bar
        document.getElementById('now-title').textContent = track.title;
        document.getElementById('now-artist').textContent = track.artist;
        
        // Update Fullscreen View
        document.getElementById('fs-title').textContent = track.title;
        document.getElementById('fs-artist').textContent = track.artist;
        
        if (track.cover) {
            document.getElementById('now-img').src = track.cover;
            document.getElementById('fs-img').src = track.cover;
            
            document.getElementById('now-img').style.display = 'block';
            document.getElementById('fs-img').style.display = 'block';
            
            document.getElementById('now-placeholder').style.display = 'none';
            document.getElementById('fs-placeholder').style.display = 'none';
        } else {
            document.getElementById('now-img').style.display = 'none';
            document.getElementById('fs-img').style.display = 'none';
            
            document.getElementById('now-placeholder').style.display = 'flex';
            document.getElementById('fs-placeholder').style.display = 'flex';
        }

        // Re-render the visual list to show the green play arrow
        renderTracklist(document.getElementById('search').value.toLowerCase());
        
        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: track.title, artist: track.artist,
                artwork: track.cover ? [{ src: track.cover, sizes: '300x300', type: 'image/jpeg' }] : []
            });
        }

        calculateAndPreloadNext();
    }

    function togglePlay() {
        if (currentIndex === -1 && viewingTracks.length > 0) return playTrack(0);
        if (audio.paused) { 
            audio.play(); 
            playBtns.forEach(btn => { btn.textContent = '⏸'; btn.setAttribute('data-tooltip', 'Pause'); });
        } else { 
            audio.pause(); 
            playBtns.forEach(btn => { btn.textContent = '▶'; btn.setAttribute('data-tooltip', 'Play'); });
        }
    }
    
    function nextTrack() {
        if (nextIndex !== -1) playTrack(nextIndex);
        else if (playingQueue.length > 0) playTrack(0);
    }
    
    function prevTrack() {
        if (audio.currentTime > 3) { 
            audio.currentTime = 0; 
        } else if (playingQueue.length > 0) {
            playTrack(currentIndex > 0 ? currentIndex - 1 : playingQueue.length - 1);
        }
    }

    function toggleShuffle() {
        isShuffle = !isShuffle;
        shuffleBtns.forEach(btn => {
            btn.style.color = isShuffle ? 'var(--primary)' : 'var(--sub)';
            btn.setAttribute('data-tooltip', isShuffle ? 'Disable shuffle' : 'Enable shuffle');
        });
        if (currentIndex !== -1) calculateAndPreloadNext();
    }

    function toggleRepeat() {
        isRepeat = !isRepeat;
        audio.loop = isRepeat;
        repeatBtns.forEach(btn => {
            btn.style.color = isRepeat ? 'var(--primary)' : 'var(--sub)';
            btn.setAttribute('data-tooltip', isRepeat ? 'Disable repeat' : 'Enable repeat');
        });
        if (currentIndex !== -1) calculateAndPreloadNext();
    }

    audio.addEventListener('timeupdate', () => {
        if (!audio.duration) return;
        const percent = ((audio.currentTime / audio.duration) * 100) + '%';
        const formattedCurr = formatTime(audio.currentTime);
        const formattedDur = formatTime(audio.duration);
        
        // Update Bottom Bar
        document.getElementById('progress-fill').style.width = percent;
        document.getElementById('time-curr').textContent = formattedCurr;
        document.getElementById('time-dur').textContent = formattedDur;
        
        // Update Fullscreen View
        document.getElementById('fs-progress-fill').style.width = percent;
        document.getElementById('fs-time-curr').textContent = formattedCurr;
        document.getElementById('fs-time-dur').textContent = formattedDur;
    });

    audio.addEventListener('ended', () => { if (!isRepeat) nextTrack(); });

    function seek(e) {
        // e.currentTarget ensures we target the exact progress bar we clicked (bottom bar OR fullscreen bar)
        const bar = e.currentTarget;
        audio.currentTime = ((e.clientX - bar.getBoundingClientRect().left) / bar.getBoundingClientRect().width) * audio.duration;
    }

    function setVolume(e) {
        const bar = document.getElementById('volume-bar');
        const vol = (e.clientX - bar.getBoundingClientRect().left) / bar.getBoundingClientRect().width;
        audio.volume = Math.max(0, Math.min(1, vol));
        document.getElementById('volume-fill').style.width = (audio.volume * 100) + '%';
    }

    function formatTime(secs) {
        const min = Math.floor(secs / 60) || 0;
        const sec = Math.floor(secs % 60) || 0;
        return `${min}:${sec < 10 ? '0' : ''}${sec}`;
    }

    if ('mediaSession' in navigator) {
        navigator.mediaSession.setActionHandler('play', togglePlay);
        navigator.mediaSession.setActionHandler('pause', togglePlay);
        navigator.mediaSession.setActionHandler('previoustrack', prevTrack);
        navigator.mediaSession.setActionHandler('nexttrack', nextTrack);
        
        // Attaching the skip functions to the native lock screen/media controls
        navigator.mediaSession.setActionHandler('seekbackward', () => skipTime(-15));
        navigator.mediaSession.setActionHandler('seekforward', () => skipTime(15));
    }
</script>
</body>
</html>