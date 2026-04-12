(function () {
    const isDashboard = document.body && document.body.dataset.page === 'dashboard';
    if (!isDashboard) return;

    const liveTime = document.getElementById('dashboardLiveTime');
    if (!liveTime) return;
    const clockText = liveTime.querySelector('span');
    const updateClock = () => {
        const now = new Date();
        const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
        if (clockText) {
            clockText.textContent = time;
        } else {
            liveTime.append(document.createTextNode(time));
        }
    };

    updateClock();
    window.setInterval(updateClock, 15000);
})();


