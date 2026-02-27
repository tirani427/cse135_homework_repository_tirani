const { get } = require("http");
const { url } = require("inspector");
const { report } = require("process");

// const collector = (function() {
(function() {
    'use strict';

    const config = {
        endpoint: 'https://collector.cse135tirani.site/collect',
        enableVitals: true,
        enableErrors: true,
        sampleRate: 1.0,
        debug: false,
        respectConsent: true,
        detectBots: true
        // batchSize: 1,
        // flushInterval: 5000,
        // app: '',
        // version:''
    };

    let initialized = false;
    let blocked = false;
    const customData = {};
    const properties = {};
    let userId = null;
    const plugins = [];
    const reportedErrors = new Set();
    let errorCount = 0;
    const MAX_ERRORS = 10;

    const vitals = {lcp: null, cls: 0, inp:null};
    let pageShowTime = Date.now();
    let totalVisibleTime = 0;
    
    const extensions = {};
    const queue = [];

    const globalProps = {};
    const beaconLog = [];
    const vitalsData = {};

    // const defaults = {
    //     endpoint: 'https://collector.cse135tirani.site/collect',
    //     enableTechnographics: true,
    //     enableTiming: true,
    //     enableVitals: true,
    //     enableErrors: true,
    //     sampleRate: 1.0,
    //     debug: false
    // };

    //Logging

    function log(...args){
        if(config.debug){
            console.log('[Collector]', ...args);
        }
    }

    function warn(...args){
        console.warn('[Collector]', ...args);
    }

    //Utility
    function round(value){
        return Math.round(value*100)/100;
    }

    function merge(dst, src){
        for(const key of Object.keys(src)){
            dst[key] = src[key];
        }
        return dst;
    }

    //Check Consent
    function hasConsent(){
        if(navigator.globalPrivacyControl){
            return false;
        }
        const cookies = document.cookie.split(';');
        for(const c of cookies){
            const cookie = c.trim();
            if(cookie.indexOf('analytics_consent=') === 0){
                return cookie.split('=')[1] === 'true';
            }
        }
        return false;
    }

    //Bot Detection
    function isBot(){
        if(navigator.webdriver) return true;
        
        const ua = navigator.userAgent;
        if(/HeadlessChrome|PhantomJS|Lighthouse/i.test(ua)) return true;

        if(/Chrome/.test(ua) && !window.chrome) return true;

        if(window._phantom || window.__nightmare || window.callPhantom) return true;
        
        return false;
    }

    //Sampling

    function isSampled(){
        if(config.sampleRate >= 1.0) return true;
        if(config.sampleRate <= 0) return false;

        const key = '_collector_sample';
        let val = sessionStorage.getItem(key);
        if(val === null){
            val = Math.random();
            sessionStorage.setItem(key, val);
        } else {
            val = parseFloat(val);
        }
        return val < config.sampleRate;
    }

    //Session Identity
    function getSessionId(){
        let sid = sessionStorage.getItem('_collector_sid');
        if(!sid){
            sid = Math.random().toString(36).substring(2) + Date.now().toString(36);
            sessionStorage.setItem('_collector_sid', sid);
        }
        return sid;
    }

    //Sampling
    function shouldSample(){
        const sampled = sessionStorage.getItem('_collector_sampled');
        if(sampled !== null){
            return sampled === 'true';
        }
        const result = Math.random() < config.sampleRate;
        sessionStorage.setItem('_collector_sampled', String(result));
        return result;
    }

    //Network Information
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

    //Technographics

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

    //Navigation Timing
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

    //Resource Timing
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

    //Web Vitals

    function initWebVitals(){
        try{
            const lcpObs = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                if(entries.length){
                    vitals.lcp = round(entries[entries.length - 1].startTime);
                }
            });
            lcpObs.observe({type: 'largest-contentful-paint', buffered:true });
        } catch (e) {
            warn('LCP not supported');
        }

        try{
            const clsObs = new PerformanceObserver((list) => {
                list.getEntries().forEach((entry) => {
                    if(!entry.hadRecentInput){
                        vitals.cls = round(vitals.cls + entry.value);
                    }
                });
            });
            clsObs.observe({type: 'layout-shift', buffered:true});
        } catch (e) {
            warn('CLS not supported');
        }

        try{
            const inpObs = new PerformanceObserver((list)=>{
                list.getEntries().forEach((entry) => {
                    if(vitals.inp === null || entry.duration > vitals.inp){
                        vitals.inp = round(entry.duration);
                    }
                });
            });
            inpObs.observe({type: 'event', buffered:true, durationThreshold: 16 });
        }catch (e) {
            warn('INP not supporetd');
        }
    }

    function getWebVitals(){
        return{lcp: vitals.lcp, cls: vitals.cls, inp: vitals.inp};
    }

    // let lcpValue = 0;
    // function observeLCP() {
    //     const observer = new PerformanceObserver((list) => {
    //         const entries = list.getEntries();
    //         const lastEntry = entries[entries.length - 1];
    //         lcpValue = lastEntry.renderTime || lastEntry.loadTime;
    //     });
    //     observer.observe({type: 'largest-contentful-paint', buffered: true});
    //     return observer;
    // }

    // let clsValue = 0;

    // function observeCLS() {
    //     const observer = new PerformanceObserver((list) => {
    //         for(const entry of list.getEntries()){
    //             if(!entry.hadRecentInput){
    //                 clsValue += entry.value;
    //             }
    //         }
    //     });
    //     observer.observe({type: 'layout-shift', buffered: true});
    //     return observer;
    // }

    // let inpValue = 0;

    // function observeINP() {
    //     const interactions = [];
    //     const observer = new PerformanceObserver((list) => {
    //         for(const entry of list.getEntries()){
    //             if(entry.interactionId){
    //                 interactions.push(entry.duration);
    //             }
    //         }

    //         if(interactions.length > 0){
    //             interactions.sort((a,b) => b - a);
    //             inpValue = interactions[0];
    //         }
    //     });
    //     observer.observe({type: 'event', buffered: true, durationThreshold: 16});
    //     return observer;
    // }

    // function getVitalsScore(metric, value){
    //     const t = thresholds[metric];
    //     if(!t) return null;
    //     if(value <= t[0]) return 'good';
    //     if(value <= t[1]) return 'needs improvement';
    //     return 'poor';
    // }

    // function initVitalsObservers(){
    //     if(typeof PerformanceObserver !== 'undefined'){
    //         try {
    //             const lcpObserver = new PerformanceObserver((list) => {
    //                 const entries = list.getEntries();
    //                 if(entries.length){
    //                     vitalsData.lcp = round(entries[entries.length - 1].startTime);
    //                     log('LCP:', vitalsData.lcp, 'ms');
    //                 }
    //             });
    //             lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });
    //         } catch (e) {
    //             warn('LCP observer not supported');
    //         }

    //         try {
    //             const fidObserver = new PerformanceObserver((list) => {
    //                 const entries = list.getEntries();
    //                 if(entries.length){
    //                     vitalsData.fid = round(entries[0].processingStart - entries[0].startTime);
    //                     log('FID:', vitalsData.fid, 'ms');
    //                 }
    //             });
    //             fidObserver.observe({ type: 'first-input', buffered: true });
    //         } catch (e) {
    //             warn('FID observer not supported');
    //         }

    //         try{
    //             let clsValue = 0;
    //             const clsObserver = new PerformanceObserver((list) => {
    //                 list.getEntries().forEach((entry) => {
    //                     if(!entry.hadRecentInput){
    //                         clsValue += entry.value;
    //                     }
    //                 });
    //                 vitalsData.cls = round(clsValue);
    //                 log('CLS:', vitalsData.cls);
    //             });
    //             clsObserver.observe({ type: 'layout-shift', buffered: true });
    //         } catch (e) {
    //             warn('CLS observer not supported');
    //         }
    //     }
    // }

    //Error Tracking

    function reportError(errorData){
        if(errorCount >= MAX_ERRORS) return;

        const key = `${errorData.type}:${errorData.message || ''}:${errorData.source || ''}:${errorData.line || ''}`;
        if(reportedErrors.has(key)) return;
        reportedErrors.add(key);
        errorCount++;

        send({
            type: 'error',
            error: errorData,
            timestamp: new Date().toISOString(),
            url: window.location.href,
            session: getSessionId()
        });

        window.dispatchEvent(new CustomEvent('collector:error', {
            detail: {errorData: errorData, count: errorCount}
        }));
    }

    function initErrorTracking(){
        window.addEventListener('error', (event) => {
            if(event instanceof ErrorEvent){
                reportError({
                    type:'js-error',
                    message: event.message,
                    source: event.filename,
                    line: event.lineno,
                    column: event.colno,
                    stack: event.error ? event.error.stack : '',
                    url: window.location.href
                });
            } else {
                const target = event.target;
                if(target && (target.tagName === 'IMG' || target.tagName ==='SCRIPT' || target.tagName === 'LINK')){
                    reportError({
                        type:'resource-error',
                        tagName: target.tagName,
                        src: target.src || target.href || '',
                        url: window.location.href
                    });
                }
            }
        }, true);

        window.addEventListener('unhandledrejection', (event) => {
            const reason = event.reason;
            reportError({
                type:'promise-rejection',
                message: reason instanceof Error ? reason.message : String(reason),
                stack: reason instanceof Error ? reason.stack : '',
                url: window.location.href
            });
        });
    }

    //Retry Queue

    function queueForRetry(payload){
        try{
            const queue = JSON.parse(sessionStorage.getItem('_collector_retry') || '[]');
            if(queue.length >= 50) return;
            queue.push(payload);
            sessionStorage.setItem('_collector_retry', JSON.stringify(queue));
        } catch (e){
            warn('Session storage unavailable or full');
        }
    }

    function processRetryQueue(){
        try{
            const queue = JSON.parse(sessionStorage.getItem('_collector_retry') || '[]');
            if(!queue.length) return;
            sessionStorage.removeItem('_collector_retry');
            queue.forEach((payload)=> {send(payload);});
        } catch (e) {
            warn('sessionStorage unavailable');
        }
    }

    //Payload Construction
    function buildPayload(eventName){
        const payload = {
            url: window.location.href,
            title: document.title,
            referrer: document.referrer,
            timestamp: new Date().toISOString(),
            type: eventName,
            session: getSessionId()
        };

            // Merge global properties
        for (const k of Object.keys(globalProps)) {
            payload[k] = globalProps[k];
        }

            // Attach vitals if available
        if (Object.keys(vitalsData).length > 0) {
            payload.vitals = vitalsData;
        }

        return payload;
    }
    
    //Payload Delivery
    function send(payload) {
        // Record in beacon log (for test page introspection)
        beaconLog.push({ time: new Date().toISOString(), payload: payload });

        const markSupported = typeof performance.mark === 'function';
        if(markSupported){
            performance.mark('collector_send_start');
        }
        // Dispatch custom event so test pages can react
        try {
            window.dispatchEvent(new CustomEvent('collector:beacon', { detail: payload }));
        } catch (e) {
            warn('Failed to dispatch collector:beacon event:', e.message);
        }

        if (config.debug) {
            console.log('[Collector] Debug Payload:', payload);
            return; // Don't actually send in debug mode
        }

        if(!config.endpoint){
            console.warn('[Collector] No endpoint configured');
            return;
        }

        const json = JSON.stringify(payload);
        let sent = false;

        const blob = new Blob([json], { type: 'application/json' });

        if (navigator.sendBeacon) {
            navigator.sendBeacon(config.endpoint, blob);
            log('Beacon sent via sendBeacon');
        } 
        
        if(!sent){
            fetch(config.endpoint, {
                method: 'POST',
                body: json,
                headers: {'Content-Type': 'application/json'},
                keepalive: true
            }).catch((err) => {
                queueForRetry(payload);
            });
            //log('Beacon sent via fetch');
        }

        if(markSupported){
            performance.mark('collector_send_end');
            performance.measure('collector_send', 'collector_send_start', 'collector_send_end');
        }
        window.dispatchEvent(new CustomEvent('collector:beacon', {detail: paylod}));
    }

    function fetchFallback(payload){
        fetch(config.endpoint, {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: { 'Content-Type': 'application/json' },
            keepalive: true
        }).catch((err) => {
            warn('Fetch(keepalive) failed:', err.message);
        });
    }

    //Public API

    function init(options){
        if(options){
            merge(config, options);
        }
        initialized = true;

        observeLCP();
        observeCLS();
        observeINP();

        initErrorTracking();
        log('Initialized with config', config);

        window.addEventListener('load', () => {
            setTimeout(() => {
                collect();
            }, 0);
        });
    //     if(initialized){
    //         warn('collector.init() called more than once');
    //         return;
    //     }

    //     config = {};
    //     for(const key of Object.keys(defaults)){
    //         config[key] = (options && options[key] !== undefined) ? options[key]: defaults[key];
    //     }

    //     if(!shouldSample()){
    //         log(`Session not sampled (rate: ${config.sampleRate})`);
    //         try{
    //             window.dispatchEvent(new CustomEvent('collector:not-sampled'));
    //         } catch (e) {

    //         }
    //         return;
    //     }

    //     initialized = true;

    //     if(config.enableErrors) initErrorTracking();
    //     if(config.enableVitals) initVitalsObservers();

    //     window.addEventListener('load', () => {
    //         setTimeout(() => {
    //             const payload = buildPayload('pageview');
    //             if(config.enableTiming){
    //                 payload.timing = getNavigationTiming();
    //                 payload.resources = getResourceSummary();
    //             }
    //             if(config.enableTechnographics){
    //                 payload.technographics = getTechnographics();
    //             }
    //             send(payload);
    //         }, 0);
    //     });

    //     log('Collector initialized', config);
        
    //     try{
    //         window.dispatchEvent(new CustomEvent('collector:initialized', { detail: config }));
    //     } catch (e) {
    //         warn('Failed to dispatch collector:initialized event:', e.message);
    //     }
    }

    function track(eventName, data){
        if(!initialized){
            warn('collector.track() called before initialization');
            return;
        }
        const payload = {
            url: window.location.href,
            timestamp: new Date().toISOString(),
            type: eventName,
            session: getSessionId(),
            data: data || {}
        };
        merge(payload, properties);
        if(userId) payload.userId = userId;
        if(config.app) payload.app = config.app;
        // const payload = buildPayload(eventName);
        // if(data) payload.data = data;
        send(payload);
    }

    function set(key, value){
        if(typeof key === 'object'){
            merge(properties, key);
        }else{
            properties[key] = value;
        }
        log('Properties updated:', properties);
        // globalProps[key] = value;
        // log(`Global property set: ${key} =`, value);

        // try{
        //     window.dispatchEvent(new CustomEvent('collector:set', { detail: { key:key, value:value } }));
        // } catch (e) {
        //     warn('Failed to dispatch collector:set event:', e.message);
        // }
    }

    function identify(id){
        userId = id;
        log('User identified:', id);
        // globalProps.userId = userId;
        // log(`User identified: ${userId}`);

        // try{
        //     window.dispatchEvent(new CustomEvent('collector:identify', { detail: { userId: userId } }));
        // } catch (e) {
        //     warn('Failed to dispatch collector:identify event:', e.message);
        // }
    }

    function use(extension){
        if(!extension || !extension.name){
            warn('Extension must have a name property');
            return;
        }
        if(extensions[extension.name]){
            warn(`Extension with name ${extension.name} already exists`);
            return;
        }
        extensions[extension.name] = extension;
        if(typeof extension.init === 'function'){
            extension.init({
                track: track,
                set: set,
                getConfig: () => config,
                getSessionId: getSessionId
            });
        }
        log('Extension registered:', extension.name);
    }

    function collect() {
        const payload = {
            type: type || 'pageview',
            url: window.location.href,
            title: document.title,
            referrer: document.referrer,
            timestamp: new Date().toISOString(),
            session: getSessionId(),
            technographics: getTechnographics(),
            timing: getNavigationTiming(),
            resources: getResourceSummary(),
            vitals: getWebVitals(),
            errorCount: errorCount,
            customData: customData
        };

        if(userId){
            payload.userId = userId;
        }

        plugins.forEach((plugin) => {
            if(typeof plugin.beforeSend === 'function'){
                const result = plugin.beforeSend(payload);
                if(result === false) return;
                if(result && typeof result === 'object'){
                    payload = result;
                }
            }
        });
        // merge(payload, properties);

        // if (userId) {
        //     payload.userId = userId;
        // }

        // if (config.app) {
        //     payload.app = config.app;
        // }

        send(payload);
        window.dispatchEvent(new CustomEvent('collector:payload', {detail: payload}));
    }

    //Time-on-page Tracking
    function initTimeOnPage(){
        document.addEventListener('visibilitychange', () => {
            if(document.visibilityState === 'hidden'){
                totalVisibleTime += Date.now() - pageShowTime;

                const exitPayload = {
                    type:'page-exit',
                    url: window.location.href,
                    timeOnPage: totalVisibleTime,
                    vitals: getWebVitals(),
                    errorCount: errorCount,
                    timestamp: new Date().toISOString(),
                    session: getSessionId()
                };

                plugins.forEach((plugin) => {
                    if(typeof plugin.onExit === 'function'){
                        plugin.onExit(exitPayload);
                    }
                });
                send(exitPayload);
            } else {
                pageShowTime = Date.now();
            }
        });
    }

    //Command Queue Processing

    function processQueue(){
        const queue = window._cq || [];
        for(const args of queue){
            const method = args[0];
            const params = args.slice(1);
            if(typeof publicAPI[method] === 'function'){
                publicAPI[method](...params);
            }
        }
        window._cq = {
            push: (args) => {
                const method = args[0];
                const params = args.slice(1);
                if(typeof publicAPI[method] === 'function'){
                    publicAPI[method](...params);
                }
            }
        };
    }

    const publicAPI = {
        init: function(options){
            if(initialized){
                console.warn('[Collector] Already initialized');
                return;
            }

            if(typeof performance.mark==='function'){
                performance.mark('collector_init_start');
            }

            if(options) merge(config, options);

            if(config.respectConsent && !hasConsent()){
                console.log('[Collector] No consent, collection disabled');
                blocked = true;
                initialized = true;
                return;
            }

            if(config.detectBots && isBot()){
                console.log('[Collector] Bot detected, collection disabled');
                blocked = true;
                initialized = true;
                return;
            }

            if(!isSampled()){
                console.log(`[Collector] Session not sampled (rate: ${config.sampleRate})`);
                blocked = true;
                initialized = true;
                return;
            }

            initialized = true;
            console.log('[Collector] Initialized', config);

            if(config.enableVitals) initWebVitals();
            if(config.enableErrors) initErrorTracking();
            initTimeOnPage();

            processRetryQueue();

            if(document.readyState === 'complete'){
                setTimeout(() => {collect('pageview');}, 0);
            } else {
                window.addEventListener('load', () => {
                    setTimeout(()=> { collect('pageview');}, 0);
                });
            }

            if(typeof performance.mark === 'function'){
                performance.mark('collector_init_end');
                performance.measure('collector_init', 'collector_init_start', 'collector_init_end');
            }
        },

        track: function(eventName, eventData){
            if(!initialized || blocked) return;

            const payload = {
                type: 'event',
                event: eventName,
                data: eventData || {},
                timestamp: new Date().toISOString(),
                url: window.location.href,
                session: getSessionId(),
                customData: customData
            };
            if(userId) payload.userId = userId;
            send(payload);
        },

        set: function(key,value){
            customData[key] = value;
        },

        identify: function(id){
            userId = id;
        },

        use: function(plugin){
            if(!plugin || typeof plugin !== 'object'){
                console.warn('[Collector] Invalid plugin');
                return;
            }
            plugins.push(plugin);
            if(typeof plugin.init === 'function'){
                plugin.init(config);
            }
            console.log(`[Collector] Plugin registered: ${plugin.name || '(unnnamed)'}`);
        }
    };

    // function sendVitals() {
    //     const vitals = {
    //         lcp: { value: round(lcpValue), score: getVitalsScore('lcp', lcpValue) },
    //         cls: { value: round(clsValue * 1000) / 1000, score: getVitalsScore('cls', clsValue) },
    //         inp: { value: round(inpValue), score: getVitalsScore('inp', inpValue) }
    //     };
    //     send({
    //         type: 'vitals',
    //         vitals: vitals,
    //         url: window.location.href,
    //         session: getSessionId(),
    //         timestamp: new Date().toISOString()
    //     });
    // }

    processQueue();

    //Expose Public API

    // window.collector = {
    //     init: init,
    //     track: track,
    //     set: set,
    //     identify: identify,
    //     use: use
    // };

  // Also expose internals for test pages
    window.__collector = {
        getNavigationTiming: getNavigationTiming,
        getResourceSummary: getResourceSummary,
        getTechnographics: getTechnographics,
        getWebVitals: getWebVitals,
        getSessionId: getSessionId,
        getNetworkInfo: getNetworkInfo,
        reportError: reportError,
        collect: collect,
        hasConsent: hasConsent,
        isBot: isBot,
        isSampled: isSampled,
        getErrorCount: () => errorCount,
        getConfig: () => config,
        isBlocked: () => blocked,
        api: publicAPI
    };

    // return {
    //     init: init,
    //     track: track,
    //     set: set,
    //     identify: identify,

    //     _getConfig: () => JSON.parse(JSON.stringify(config)), // Deep copy for safety
    //     _getGlobalProps: () => JSON.parse(JSON.stringify(globalProps)),
    //     _getBeaconLog: () => beaconLog.slice(),
    //     _isInitialized: () => initialized,
    //     _isSampled: () => {
    //         const s = sessionStorage.getItem('_collector_sampled');
    //         return s === 'true';
    //     }
    // };
})();




    



//     const ENDPOINT = 'https://collector.cse135tirani.site/collect';


//     // ERROR REPORTING/TRACKING
//     const reportedErrors = new Set();
//     let errorCount = 0;
//     const MAX_ERRORS = 10;
    
//     function reportError(errorData){
//         if(errorCount >= MAX_ERRORS){ return; }

//         const key = `${errorData.type}:${errorData.message}:${errorData.source || ''}:${errorData.line || ''}`;
//         if(reportedErrors.has(key)) return;
//         reportedErrors.add(key);
//         errorCount++;

//         send({
//             type:'error',
//             error: errorData,
//             timestamp: new Date().toISOString(),
//             url: window.location.href
//         });

//         window.dispatchEvent(new CustomEvent('collector:error', { detail: { errorData: errorData, count: errorCount } }));
//     }

        


//     // PERFORMANCE OBSERVERS
//     const observer = new PerformanceObserver((list) => {
//         for(const entry of list.getEntries()) {
//             console.log(entry.entryType, entry);
//         }
//     });
//     observer.observe({ type: 'largest-contentful-paint', buffered: true });

//     let lcpValue = 0;
//     function observeLCP() {
//         const observer = new PerformanceObserver((list) => {
//             const entries = list.getEntries();
//             const lastEntry = entries[entries.length - 1];
//             lcpValue = lastEntry.renderTime || lastEntry.loadTime;
//         });
//         observer.observe({type: 'largest-contentful-paint', buffered: true});
//         return observer;
//     }

//     let clsValue = 0;

//     function observeCLS() {
//         const observer = new PerformanceObserver((list) => {
//             for(const entry of list.getEntries()){
//                 if(!entry.hadRecentInput){
//                     clsValue += entry.value;
//                 }
//             }
//         });
//         observer.observe({type: 'layout-shift', buffered: true});
//         return observer;
//     }

//     let inpValue = 0;

//     function observeINP() {
//         const interactions = [];
//         const observer = new PerformanceObserver((list) => {
//             for(const entry of list.getEntries()){
//                 if(entry.interactionId){
//                     interactions.push(entry.duration);
//                 }
//             }

//             if(interactions.length > 0){
//                 interactions.sort((a,b) => b - a);
//                 inpValue = interactions[0];
//             }
//         });
//         observer.observe({type: 'event', buffered: true, durationThreshold: 16});
//         return observer;
//     }

//     function initWebVitals() {
//         // Largest Contentful Paint (LCP)
//         try {
//         const lcpObserver = new PerformanceObserver((list) => {
//             const entries = list.getEntries();
//             if (entries.length) {
//             vitals.lcp = round(entries[entries.length - 1].startTime);
//             }
//         });
//         lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });
//         } catch (e) {
//         console.log('[collector-v6] LCP observer not supported');
//         }

//         // Cumulative Layout Shift (CLS)
//         try {
//         const clsObserver = new PerformanceObserver((list) => {
//             list.getEntries().forEach((entry) => {
//             if (!entry.hadRecentInput) {
//                 vitals.cls = round(vitals.cls + entry.value);
//             }
//             });
//         });
//         clsObserver.observe({ type: 'layout-shift', buffered: true });
//         } catch (e) {
//         console.log('[collector-v6] CLS observer not supported');
//         }

//         // Interaction to Next Paint (INP)
//         try {
//         const inpObserver = new PerformanceObserver((list) => {
//             list.getEntries().forEach((entry) => {
//             const duration = entry.duration;
//             if (vitals.inp === null || duration > vitals.inp) {
//                 vitals.inp = round(duration);
//             }
//             });
//         });
//         inpObserver.observe({ type: 'event', buffered: true, durationThreshold: 16 });
//         } catch (e) {
//         console.log('[collector-v6] INP observer not supported');
//         }
//     }

//     const thresholds = {
//         lcp: [2500, 4000],
//         cls: [0.1, 0.25],
//         inp: [200, 500]
//     };

//     function getVitalsScore(metric, value){
//         const t = thresholds[metric];
//         if(!t) return null;
//         if(value <= t[0]) return 'good';
//         if(value <= t[1]) return 'needs improvement';
//         return 'poor';
//     }

//     function sendVitals(){
//         const vitals = {
//             lcp: {value: round(lcpValue), score: getVitalsScore('lcp', lcpValue)},
//             cls: {value: round(clsValue*1000)/1000, score: getVitalsScore('cls', clsValue)},
//             inp: {value: round(inpValue), score: getVitalsScore('inp', inpValue)}
//         };
//         send({
//             type:'vitals',
//             vitals:vitals,
//             url:window.location.href,
//             session: getSessionId(),
//             timestamp: new Date().toISOString()
//         });
//     }

    

    


//     const sessionId = getSessionId();



//     // DATA DELIVERY
//     function send(payload){
//         const json = JSON.stringify(payload);
//         const blob = new Blob([json], { type: 'application/json' });

//         if(navigator.sendBeacon){
//             const sent = navigator.sendBeacon(ENDPOINT, blob);
//             if(sent){
//                 console.log('[Collector v1] sendBeacon sent successfully');
//                 return;
//             }
//             console.log('[Collector v1] sendBeacon failed, falling back to fetch');
//         }

//         fetch(ENDPOINT, {
//             method: 'POST',
//             body: json,
//             headers: { 'Content-Type': 'application/json' },
//             keepalive: true
//         }).then((resp) => {
//             console.log('[Collector v3] Fetch(keepalive) status:', resp.status);
//         }).catch((err) =>{
//             console.log('[Collector v3] Fetch(keepalive) failed, trying plain fetch:');
//             fetch(ENDPOINT, {
//                 method: 'POST',
//                 body: json,
//                 headers: { 'Content-Type': 'application/json' }
//             }).then((resp) => {
//                 console.log('[Collector v3] Plain Fetch status:', resp.status);
//             }).catch((err)=>{
//                 console.error('[Collector v3] all delivery methods failed:', err.message);
//             });
//         });
//     }
    
//     // MAIN COLLECT FUNCTION
//     function collect() {
//         const payload = {
//             url: window.location.href,
//             title: document.title,
//             referrer: document.referrer,
//             timestamp: new Date().toISOString(),
//             type: 'pageview',
//             session: getSessionId(),
//             technographics: getTechnographics(),
//             timing: getNavigationTiming(),
//             resources: getResourceSummary(),
//             vitals: getWebVitals(),
//             errorCount: errorCount
//         };

//         send(payload);

//         window.dispatchEvent(new CustomEvent('collector:payload', { detail: payload }));


//         // console.log('[Collector v1] Sending beacon:', payload);

//         // const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });

//         // if(navigator.sendBeacon){
//         //     const sent = navigator.sendBeacon(ENDPOINT, blob);
//         //     console.log('[Collector v1] sendBeacon sent:', sent);
//         // } else {
//         //     console.warn('[Collector v1] sendBeacon not available, using fetch fallback');
//         // }
//         // console.log('[collector v2] payload:', payload);
//         // window.dispatchEvent(new CustomEvent('collector:payload', { detail: payload }));
//     }

//     initErrorTracking();
//     initWebVitals();

//     window.__collectorSendEvent = (eventType, eventData) => {
//         const payload = {
//             url: window.location.href,
//             title: document.title,
//             referrer: document.referrer,
//             timestamp: new Date().toISOString(),
//             type: eventType || 'custom',
//             sessionId: sessionId,
//             data: eventData || {}
//         };
//         send(payload);
//     };

//     const lcpObserver = observeLCP();
//     const clsObserver = observeCLS();
//     const inpObserver = observeINP();

//     if(document.readyState === 'complete'){
//         collect('pageview');
//     } else {
//         window.addEventListener('load', () => collect('pageview'));
//     }

//     window.addEventListener('load', () =>{
//         setTimeout(() => {
//             console.log('[Collector v4] Page loaded, collecting performance timing');
//             collect();
//         }, 0);
//     });

//     // window.addEventListener('error', (event) => {
//     //     if(event instanceof ErrorEvent){
//     //         reportError({
//     //             type:'js-error',
//     //             message: event.message,
//     //             source: event.filename,
//     //             line: event.lineno,
//     //             column: event.colno,
//     //             stack: event.error ? event.error.stack : '',
//     //             url: window.location.href
//     //         });
//     //     }
//     // });

//     window.addEventListener('unhandledrejection', (event) => {
//         const reason = event.reason;
//         reportError({
//             type:'promise-rejection',
//             message: reason instanceof Error ? reason.message : String(reason),
//             stack: reason instanceof Error ? reason.stack : '',
//             url: window.location.href
//         });
//     });

//     window.addEventListener('error', (event) => {
//         if(!(event instanceof ErrorEvent)){
//             const target = event.target;
//             if(target && (target.tagName === 'IMG' || target.tagName ==='SCRIPT' || target.tagName === 'LINK')){
//                 reportError({
//                     type:'resource-error',
//                     tagName: target.tagName,
//                     src: target.src || target.href || '',
//                     url: window.location.href
//                 });
//             }
//         }
//     }, true);

//     // const originalConsoleError = console.error;
//     // console.error = (...args) => {
//     //     reportError({
//     //         type:'console-error',
//     //         message: args.map(String).join(' '),
//     //         url: window.location.href
//     //     });
//     //     originalConsoleError(...args);
//     // };

//     document.addEventListener('visibilitychange', () => {
//         if(document.visibilityState === 'hidden') {
//             sendVitals();
//         }
//     });

//     window.__collector = {
//         getTechnographics: getTechnographics,
//         getSessionId: getSessionId,
//         getNetworkInfo: getNetworkInfo,
//         getNavigationTiming: getNavigationTiming,
//         getResourceSummary: getResourceSummary,
//         getVitalsScore: getVitalsScore,
//         getVitals: () => ({
//             lcp: {value: round(lcpValue), score: getVitalsScore('lcp', lcpValue)},
//             cls: {value: round(clsValue*1000)/1000, score: getVitalsScore('cls', clsValue)},
//             inp: {value: round(inpValue), score: getVitalsScore('inp', inpValue)}
//         }),
//         collect: collect,
//         sendVitals: sendVitals,
//         getErrorCount: () => errorCount,
//         getReportedErrors: () => Array.from(reportedErrors)
//     };
// })();