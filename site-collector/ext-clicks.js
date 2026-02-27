window.ClickTacker = {
    name: 'click-tracker',
    _handler: null,
    _debounceTimer: null,

    init: function(collector) {
        var self = this;
        let lastClick = 0;

        self._handler = function(event) {
            const now = Date.now();
            if(now - lastClick < 300){ return; }
            lastClick = now;

            const target = event.target;
            collector.track('click', {
                tagName: target.tagName,
                id: target.id || undefined,
                className: target.className || undefined,
                text: (target.textContext || '').substring(0,100),
                x:event.clientX,
                y:event.clientY,
                selector: self._getSelector(target)
            });
        };

        document.addEventListener('click', self._handler, true);
    },

    _getSelector: function(element){
        const parts = [];
        while(element && element !== document.body){
            let part = element.tagName.toLowerCase();
            if(element.id){
                part += `#${element.id}`;
                parts.unshift(part);
                break;
            }
            if(element.className && typeof element.className === 'string'){
                part += `.${element.className.trim().split(/\s+/).join('.')}`;
            }
            parts.unshift(part);
            element = element.parentElement;
        }
        return parts.join(' > ');
    },

    destroy: function() {
        if(this._handler){
            document.removeEventListener('click', this._handler, true);
            this._handler = null;
        }
    }
};