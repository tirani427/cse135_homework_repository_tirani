const { url } = require("inspector");

(function() {
    'use strict';

    const ENDPOINT = 'https://collector.cse135tirani.site/collect';
    
    function getNavigationTiming(){
        const entries = performance.getEntriesByType('navigation');
        if(!entries.length) return {};

        const n = entries[0];

        return {
            dnsLookup: round(n.domainLookupEnd - n.domainLookupStart),
            tcpConnect: round(n.connectEnd - n.connectStart),
            tlsHandshake: n.secureConnectionStart > 0 ? round(n.connectEnd - n.secureConnectionStart) : 0,
            ttfb: round(n.responseStart - n.requestStart),
            download: round(n.responseEnd - n.responseStart),
            domInteractive: round(n.domInteractive - n.fetchStart),
            domComplete: round(n.domComplete - n.fetchStart),
            loadEvent: round(n.loadEventEnd - n.fetchStart),
            fetchTime: round(n.responseEnd - n.fetchStart),
            transferSize: n.transferSize,
            headerSize: n.transferSize - n.encodedBodySize
        };
    }

    function round(value){
        return Math.round(value*100)/100;
    }

    function getResourceSummary(){
        const resources = performance.getEntriesByType('resource');

        const summary = {
            script: { count: 0, totalSize: 0, totalDuration: 0 },
            link: { count: 0, totalSize: 0, totalDuration: 0 },
            img: { count: 0, totalSize: 0, totalDuration: 0 },
            font: { count: 0, totalSize: 0, totalDuration: 0 },
            fetch: { count: 0, totalSize: 0, totalDuration: 0 },
            xmlhttprequest: { count: 0, totalSize: 0, totalDuration: 0 },
            other: { count: 0, totalSize: 0, totalDuration: 0 }
        };

        resources.forEach((res) => {
            const type = summary[res.initiatorType] ? res.initiatorType : 'other';
            summary[type].count += 1;
            summary[type].totalSize += res.transferSize || 0;
            summary[type].totalDuration += res.duration || 0;
        });
        return {
            totalResources: resources.length,
            byType: summary
        };
    }

    function getSessionId(){
        let sid = sessionStorage.getItem('_collector_sid');
        if(!sid){
            sid = Math.random().toString(36).substring(2) + Date.now().toString(36);
            sessionStorage.setItem('_collector_sid', sid);
        }
        return sid;
    }

    const sessionId = getSessionId();

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
        const data =  {
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
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            touchSupport: 'ontouchstart' in window || navigator.maxTouchPoints > 0
        };
        if(navigator.connection){
                data.connectionType = navigator.connection.effectiveType || '';
                data.connectionDownlink = navigator.connection.downlink || '';
        }

        return data;
    }

    function send(payload){
        const json = JSON.stringify(payload);
        const blob = new Blob([json], { type: 'application/json' });

        if(navigator.sendBeacon){
            const sent = navigator.sendBeacon(ENDPOINT, blob);
            if(sent){
                console.log('[Collector v1] sendBeacon sent successfully');
                return;
            }
            console.log('[Collector v1] sendBeacon failed, falling back to fetch');
        }

        fetch(ENDPOINT, {
            method: 'POST',
            body: json,
            headers: { 'Content-Type': 'application/json' },
            keepalive: true
        }).then((resp) => {
            console.log('[Collector v3] Fetch(keepalive) status:', resp.status);
        }).catch((err) =>{
            console.log('[Collector v3] Fetch(keepalive) failed, trying plain fetch:');
            fetch(ENDPOINT, {
                method: 'POST',
                body: json,
                headers: { 'Content-Type': 'application/json' }
            }).then((resp) => {
                console.log('[Collector v3] Plain Fetch status:', resp.status);
            }).catch((err)=>{
                console.error('[Collector v3] all delivery methods failed:', err.message);
            });
        });
    }
    
    function collect() {
        const payload = {
            url: window.location.href,
            title: document.title,
            referrer: document.referrer,
            timestamp: new Date().toISOString(),
            type: 'pageview',
            session: getSessionId(),
            technographics: getTechnographics(),
            timing: getNavigationTiming(),
            resources: getResourceSummary()
        };

        send(payload);

        // console.log('[Collector v1] Sending beacon:', payload);

        // const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });

        // if(navigator.sendBeacon){
        //     const sent = navigator.sendBeacon(ENDPOINT, blob);
        //     console.log('[Collector v1] sendBeacon sent:', sent);
        // } else {
        //     console.warn('[Collector v1] sendBeacon not available, using fetch fallback');
        // }
        // console.log('[collector v2] payload:', payload);
        // window.dispatchEvent(new CustomEvent('collector:payload', { detail: payload }));
    }

    window.__collectorSendEvent = (eventType, eventData) => {
        const payload = {
            url: window.location.href,
            title: document.title,
            referrer: document.referrer,
            timestamp: new Date().toISOString(),
            type: eventType || 'custom',
            sessionId: sessionId,
            data: eventData || {}
        };
        send(payload);
    };

    if(document.readyState === 'complete'){
        collect('pageview');
    } else {
        window.addEventListener('load', () => collect('pageview'));
    }

    window.addEventListener('load', () =>{
        setTimeout(() => {
            console.log('[Collector v4] Page loaded, collecting performance timing');
            collect();
        }, 0);
    });

    document.addEventListener('visibilitychange', () => {
        if(document.visibilityState === 'hidden') {
            collect('pagehide');
        }
    });

    window.__collector = {
        getTechnographics: getTechnographics,
        getSessionId: getSessionId,
        getNetworkInfo: getNetworkInfo,
        collect: collect
    };
})();