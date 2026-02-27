(function() {
    'use strict';

    const ENDPOINT = 'https://collector.cse135tirani.site/collect';
    
    function getSessionId(){
        let sid = sessionStorage.getItem('_collector_sid');
        if(!sid){
            sid = Math.random().toString(36).substring(2) + Date.now().toString(36);
            sessionStorage.setItem('_collector_sid', sid);
        }
        return sid;
    }

    function getNetworkInfo(){
        if(!('connection' in navigator)) return {};

        const conn = navigator.connection;
        return {
            effectiveType: conn.effectiveType,
            downlink: conn.downlink,
            rtt: conn.rtt,
            saveData: conn.saveData
        };
    }

    function getTechnographics(){
        return {
            //Browser Identification
            userAgent: navigator.userAgent,
            language: navigator.language,
            cookiesEnabled: navigator.cookieEnabled,
            //Viewport (current browser window)
            viewportWidth: window.innerWidth,
            viewportHeight: window.innerHeight,
            //Screen (physical display)
            screenWidth: screen.width,
            screenHeight: screen.height,
            pixelRatio: window.devicePixelRatio,
            //Hardware
            cores: navigator.hardwareConcurrency || 0,
            memory: navigator.deviceMemory || 0,
            //Network
            network: getNetworkInfo(),
            //Preferences
            colorScheme: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light',
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
        };
    }
    
    function collect() {
        const payload = {
            url: window.location.href,
            title: document.title,
            referrer: document.referrer,
            timestamp: new Date().toISOString(),
            type: 'pageview',
            session: getSessionId(),
            technographics: getTechnographics()
        };

        console.log('[Collector v1] Sending beacon:', payload);

        const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });

        if(navigator.sendBeacon){
            const sent = navigator.sendBeacon(ENDPOINT, blob);
            console.log('[Collector v1] sendBeacon sent:', sent);
        } else {
            console.warn('[Collector v1] sendBeacon not available, using fetch fallback');
        }
        console.log('[collector v2] payload:', payload);
        window.dispatchEvent(new CustomEvent('collector:payload', { detail: payload }));
    }

    window.addEventListener('load', () => {
        console.log('[Collector v1] Page loaded, collecting technographics');
        collect();
    });

    document.addEventListener('visibilitychange', () => {
        if(document.visibilityState === 'hidden') {
            console.log('[Collector v1] Page hidden, sending exit beacon');
            collect();
        }
    });

    window.__collector = {
        getTechnographics: getTechnographics,
        getSessionId: getSessionId,
        getNetworkInfo: getNetworkInfo,
        collect: collect
    };
})();