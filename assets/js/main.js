// Main JS functionality

$(document).ready(function () {
    if ($('#stream-player').length === 0 || typeof videojs === 'undefined') {
        return;
    }

    const streamSrc = $('#stream-player source').attr('src');
    const reconnectDelayMs = 5000;
    const maxRecoverAttempts = 8;
    let recoverAttempts = 0;
    let recoverTimer = null;

    const player = videojs('stream-player', {
        liveui: true,
        autoplay: false,
        controls: true,
        muted: false,
        responsive: true,
        fluid: true,
        html5: {
            vhs: {
                enableLowInitialPlaylist: true,
                smoothQualityChange: true,
                experimentalBufferBasedABR: true,
                withCredentials: false
            },
            nativeAudioTracks: false,
            nativeVideoTracks: false
        }
    });

    function clearRecoverTimer() {
        if (recoverTimer) {
            clearTimeout(recoverTimer);
            recoverTimer = null;
        }
    }

    function scheduleRecover() {
        if (!streamSrc || recoverAttempts >= maxRecoverAttempts) {
            return;
        }

        clearRecoverTimer();
        recoverTimer = setTimeout(function () {
            recoverAttempts += 1;
            player.src({ src: streamSrc, type: 'application/x-mpegURL' });
            player.load();
            player.play().catch(function () {
                // Autoplay may be blocked by browser policy; user can press play.
            });
        }, reconnectDelayMs);
    }

    player.on('playing', function () {
        recoverAttempts = 0;
        clearRecoverTimer();
    });

    player.on('waiting', function () {
        scheduleRecover();
    });

    player.on('stalled', function () {
        scheduleRecover();
    });

    player.on('error', function () {
        scheduleRecover();
    });
});
