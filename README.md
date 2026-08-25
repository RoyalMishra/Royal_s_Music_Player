# Royal's Music Player
A lightweight, zero-dependency music streaming web application built with Core PHP and Vanilla JavaScript. Designed to run efficiently on restricted shared hosting environments, this player features a highly optimized backend and a responsive, Spotify-inspired frontend.

# Personal Cloud Music Player

A lightweight, zero-dependency music streaming application engineered with Core PHP and Vanilla JavaScript. This project is specifically optimized for highly restricted shared hosting environments, bypassing network timeouts and memory limits through client-side state management and precise binary parsing.

## 🚀 Technical Architecture

*   **Zero-Dependency Stack:** Built entirely without Composer packages, external PHP extensions, or JavaScript frameworks to ensure absolute portability.
*   **Magic Byte Extraction:** Replaces volatile Base64 string conversions with a custom PHP binary parser that reads raw hexadecimal signatures (`\xFF\xD8\xFF` for JPEG) to guarantee uncorrupted album art extraction directly from ID3 tags.
*   **Asynchronous API Routing:** Utilizes a custom single-file PHP router that delivers JSON payloads via AJAX, preventing server-side DOM reloading and mitigating standard shared-hosting bandwidth throttling.
*   **Client-Side State Cache:** Employs an in-memory JavaScript cache that dynamically maps directory structures, enabling zero-latency folder switching and instantaneous UI rendering.

## ⚡ Key Implementations

*   **Intelligent Pre-Buffering:** A hidden secondary `<audio>` node calculates queue logic (respecting shuffle and repeat states) and silently caches the upcoming track, achieving gapless playback.
*   **MediaSession API Integration:** Maps DOM transport controls to native operating system APIs, enabling lock-screen, smartwatch, and Bluetooth hardware control for play, pause, and ±15s seeking.
*   **Responsive App-Like UI:** Uses CSS Grid and Flexbox to adapt seamlessly from a desktop layout into a mobile-optimized view, featuring a hidden bottom-bar and a gesture-triggered fullscreen overlay.
*   **Dynamic Marquee Rendering:** CSS-driven keyframe animations automatically trigger scrolling text behavior in the fullscreen player to elegantly display lengthy track metadata.

## 📦 Deployment Instructions

1. Clone the repository and upload `index.php` to your web server's public directory.
2. Create a `/music` directory in the root alongside the PHP file.
3. Upload `.mp3` or `.m4a` files directly, or organize them into single-level subfolders to automatically generate categorized playlist tabs.
4. Access the URL; the system will automatically parse metadata, build the cache index, and serve the application seamlessly.

<br>

*Curated with 💖 By Royal*



## User Interface

<img width="356" height="762" alt="image" src="https://github.com/user-attachments/assets/4add1b3a-6a48-41fa-8db7-9363358c40d2" />

<img width="347" height="762" alt="image" src="https://github.com/user-attachments/assets/dd9ce09a-342a-4e5f-8209-ec1d290b2e96" />

<img width="347" height="762" alt="image" src="https://github.com/user-attachments/assets/22bc9387-9d9b-49e4-95a0-798d3b33c292" />

https://github.com/user-attachments/assets/8d424bda-cc60-440c-b947-6b88f4dc85b0





## Report Generated

<img width="1907" height="1611" alt="image" src="https://github.com/user-attachments/assets/1c44b46d-196d-46a4-8288-827dcef6241e" />

