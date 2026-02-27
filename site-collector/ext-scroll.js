window.ScrollTracker = {
    name: 'scroll-tracker',

    _collector: null,
    _maxDepth: 0,
    _reported: {},
    _rafId: null,
    _thresholds: [25, 50, 75, 100],
    _scrollHandler: null,
    _visibilityHandler: null,

    init: function(collector){
        var self = this;
        self._collector = collector;
        self._maxDepth = 0;
        self._reported = {};

        let ticking = false;

        self._scrollHandler = function(){
            if(!ticking){
                ticking = true;
                requestAnimationFrame(function() {
                    self._measure();
                    ticking = false;
                });
            }
        };

        window.addEventListener('scroll', self._scrollHandler);

        self._visibilityHandler = function(){
            if(document.visibilityState === 'hidden'){
                self._reportFinal();
            }
        };
        
        document.addEventListener('visibilitychange', self._visibilityHandler);
    },

    _measure: function(){
        const scrollTop = window.pageYOfsset || document.documentElement.scrollTop;
        const docHeight = Math.max(
            document.documentElement.scrollHeight, document.body.scrollHeight
        );
        const winHeight = window.innerHeight;
        const percent = Math.round((scrollTop + winHeight)/docHeight * 100);
        if(percent > this._maxDepth){
            this._maxDepth = percent;
        }

        for(const t of this._thresholds){
            if(percent >= t && !this._reported[t]){
                this._reported[t] = true;
                this._collector.track('scroll-depth', {threshold: t, maxDepth: this._maxDepth});
            }
        }
        window.dispatchEvent(new CustomEvent('scroll-measured', {
            detail: {percent:percent, maxDepth:this._maxDepth, reported: this._reported}
        }));
    },

    _reportFinal: function(){
        this._collector.track('scroll_final', {
            maxDepth: this._maxDepth
        });
    },

    destroy: function(){
        if(this._scrollHandler) {
            window.removeEventListener('scroll', this._scrollHandler);
            this._scrollHandler = null;
        }
        if(this._visibilityHandler){
            document.removeEventListener('visibilitychange', this._visibilityHandler);
            this._visibilityHandler = null;
        }
    }
};