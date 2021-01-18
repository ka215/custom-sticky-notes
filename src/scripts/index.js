/**
 * Custom Sticky Notes scripts coded with non-jQuery
 * @package CustomStickyNotes
 * @since   1.1.2
 */
import { postData } from './_postData'
import Storage from './_webStorage'

const init = function() {
    const ls       = new Storage(),
          ss       = new Storage('SessionStorage'),
          barMenu  = document.getElementById('wp-admin-bar-custom-sticky-notes'),
          csnpForm = document.getElementById('csnp-action-form'),
          noteBody = document.getElementById('csnp-content-body'),
          defaults = { localCache: false, darkTheme: false, useSession: false, autosave: false }

    let   keepShow = false,
          locked   = false,
          opts     = {}

    loadOptions()
    setCache()

    /*
     * DOM Watcher
     */
    const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                switch(mutation.type) {
                    case 'attributes':
                        if ( mutation.attributeName === 'class' && !keepShow ) {
                            if (mutation.target.classList.contains('hover')) {
                                showPanel()
                            } else {
                                hidePanel()
                            }
                        }
                        break
                    case 'childList':
                        //console.log(mutation)
                        break
                }
            })
          }),
          config = { attributes: true, childList: true }

    if (barMenu) {
        observer.observe(barMenu, config)
    }

    if (document.getElementById('csnp-lock-panel')) {
    /*
     * Event Handlers
     */
    document.getElementById('csnp-lock-panel').addEventListener('change', (evt) => {
        let self = evt.target,
            icon = document.getElementById('csnp-lock-icon'),
            text = document.getElementById('csnp-lock-text')

        locked = self.checked
        if (locked) {
            icon.classList.remove('dashicons-unlock')
            icon.classList.add('dashicons-lock')
            text.textContent = text.getAttribute('data-on')
            keepShow = true
        } else {
            icon.classList.remove('dashicons-lock')
            icon.classList.add('dashicons-unlock')
            text.textContent = text.getAttribute('data-off')
            keepShow = false
        }
    }, false)

    noteBody.addEventListener('focus', () => {
        keepShow = true
    }, false)

    noteBody.addEventListener('blur', () => {
        if (!locked) {
            keepShow = false
        }
        if (opts.autosave) {
            updateCache()
        }
    }, false)

    noteBody.addEventListener('keydown', (evt) => {
        if (evt.keyCode == 13) {
            let self = evt.target,
                _pos = self.selectionStart

            self.value = `${self.value.substr(0, _pos)}\n${self.value.substr(_pos)}`
            self.setSelectionRange(_pos + 1, _pos + 1)
            if (opts.autosave) {
                updateCache()
            }
        }
        resizeHeight()
    }, false)

    Array.prototype.forEach.call(document.querySelectorAll('a.csnp-dismiss, button#csnp-close'), (elm) => {
        elm.addEventListener('click', () => {
            if (locked) {
                document.getElementById('csnp-lock-panel').checked = false
                triggerEvent(document.getElementById('csnp-lock-panel'), 'change')
            }
            keepShow = false
            hidePanel()
        }, false)
    })

    document.getElementById('csnp-save').addEventListener('click', () => {
        setCache()
        if (!opts.localCache) {
            // Save to DataBase
            const rawUrlArray = csnpForm.getAttribute('action').split('?'),
                  baseUrl = rawUrlArray[0],
                  params = new URLSearchParams(rawUrlArray[1]),
                  data = { 'sticky_notes': noteBody.value }
            let notes = document.getElementById('cache-notes')

            for (let [key, val] of params) {
                data[key] = val
            }
            postData(baseUrl, data, 'text').then((response) => {
                //console.log(response)
                notes.textContent = response
                fadeoutNotes()
            }).catch((error) => {
                //console.error(error)
                notes.textContent = error
                fadeoutNotes()
            })
        }
    }, false)

    document.getElementById('csnp-clear').addEventListener('click', () => {
        document.getElementById('csnp-content-body').value = ''
        resizeHeight()
    }, false)

    document.getElementById('csnp-setting').addEventListener('click', () => {
        document.getElementById('csnp-config-block').classList.toggle('open')
    }, false)

    document.getElementById('local-only').addEventListener('change', (evt) => {
        let onLocalOnly = evt.target.checked

        opts.localCache = onLocalOnly
        saveOptions()
    }, false)

    document.getElementById('on-dark-theme').addEventListener('change', (evt) => {
        let onDarkTheme    = evt.target.checked,
            panelContainer = document.getElementById('csnp-panel')

        if (onDarkTheme) {
            panelContainer.classList.add('csnp-theme-dark')
        } else {
            panelContainer.classList.remove('csnp-theme-dark')
        }
        opts.darkTheme = onDarkTheme
        saveOptions()
    }, false)

    document.getElementById('on-session-storage').addEventListener('change', (evt) => {
        let onSessionStorage = evt.target.checked,
            cacheKey = 'csnp-local-cache'

        if (onSessionStorage) {
            if (ls.has(cacheKey)) {
                ss.save(cacheKey, ls.load(cacheKey))
            }
            ls.removeOf('^csnp-')
        } else {
            if (ss.has(cacheKey)) {
                ls.save(cacheKey, ss.load(cacheKey))
            }
            ss.removeOf('^csnp-')
        }
        opts.useSession = onSessionStorage
        saveOptions()
    }, false)

    document.getElementById('on-auto-save').addEventListener('change', (evt) => {
        let onAutoSave = evt.target.checked

        opts.autosave = onAutoSave
        saveOptions()
    }, false)
    // End Event Handlers
    }

    /*
     * Functions
     */
    function loadOptions() {
        if (!Object.prototype.hasOwnProperty.call(opts, 'localCache')) {
            if (ls.has('csnp-options')) {
                opts = ls.load('csnp-options')
            } else
            if (ss.has('csnp-options')) {
                opts = ss.load('csnp-options')
            } else {
                opts = defaults
            }
        } else {
            if (opts.useSession) {
                opts = ss.load('csnp-options')
            } else {
                opts = ls.load('csnp-options')
            }
        }
    }

    function saveOptions() {
        if (opts.useSession) {
            ss.save('csnp-options', opts)
        } else {
            ls.save('csnp-options', opts)
        }
    }

    function getCache() {
        let cacheKey = 'csnp-local-cache',
            cache    = null

        if (opts.useSession) {
            if (ss.has(cacheKey)) {
                cache = ss.load(cacheKey)
            }
        } else {
            if (ls.has(cacheKey)) {
                cache = ls.load(cacheKey)
            }
        }
        return cache
    }

    function setCache() {
        if (!document.getElementById('csnp-content-body')) {
            return
        }
        let cacheKey   = 'csnp-local-cache',
            cacheValue = document.getElementById('csnp-content-body').value

        if (cacheValue) {
            if (opts.useSession) {
                ss.save(cacheKey, cacheValue)
            } else {
                ls.save(cacheKey, cacheValue)
            }
        }
    }

    function updateCache() {
        let notes = document.getElementById('cache-notes')

        notes.textContent = 'Auto Saved'
        setCache()
        fadeoutNotes()
    }

    function showPanel() {
        let menu  = document.getElementById('csnp-container'),
            panel = document.getElementById('csnp-panel'),
            cache = getCache(),
            _body = document.getElementById('csnp-content-body')

        _body.innerHTML = cache
        resizeHeight()

        document.getElementById('local-only').checked = opts.localCache
        document.getElementById('on-dark-theme').checked = opts.darkTheme
        triggerEvent(document.getElementById('on-dark-theme'), 'change')
        document.getElementById('on-session-storage').checked = opts.useSession
        document.getElementById('on-auto-save').checked = opts.autosave

        menu.classList.add('active')
        panel.classList.add('shown')
    }

    function hidePanel() {
        let menu  = document.getElementById('csnp-container'),
            panel = document.getElementById('csnp-panel')

        panel.classList.remove('shown')
        menu.classList.remove('active')
    }

    function resizeHeight() {
        let taElm = document.getElementById('csnp-content-body'),
            maxHeight = (window.innerHeight / 5 * 3) - 32,
            compStyles = window.getComputedStyle(taElm),
            paddingY = (parseInt(compStyles.getPropertyValue('padding-top'), 10) + parseInt(compStyles.getPropertyValue('padding-bottom'), 10)),
            newHeight = taElm.scrollHeight - paddingY

        if ( taElm.scrollHeight < maxHeight ) {
            if ( maxHeight < newHeight ) {
                newHeight = maxHeight
            }
            taElm.style.height = `${newHeight}px`
        } else {
            taElm.style.height = `${maxHeight}px`
        }
    }

    function triggerEvent( element, event ) {
        if ( document.createEvent ) {
            let evt = document.createEvent( 'HTMLEvents' )
            evt.initEvent( event, true, true )
            return element.dispatchEvent( evt )
        } else {
            let evt = document.createEventObject()
            return element.fireEvent( `on${event}`, evt )
        }
    }

    function fadeoutNotes() {
        const notes = document.getElementById('cache-notes')

        notes.classList.add('text-fadeout')
        setTimeout(() => {
            notes.textContent = ''
            notes.classList.remove('text-fadeout')
        }, 1500)
    }

}

// Dispatcher
if ( document.readyState === 'complete' || ( document.readyState !== 'loading' && ! document.documentElement.doScroll ) ) {
    init()
} else
if ( document.addEventListener ) {
    document.addEventListener( 'DOMContentLoaded', init, false )
} else {
    window.onload = init
}
