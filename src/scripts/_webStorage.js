export default class Storage {
    constructor(type, obfuscation) {
        this.type = type && type.toLowerCase() === 'sessionstorage' ? 'sessionStorage' : 'localStorage'
        this.obfuscation = obfuscation && !obfuscation ? false : true
        if ( this.isAvailable() ) {
            this.storage = window[this.type]
        } else {
            throw new Error(`${this.type} is not available for us`)
        }
    }
    
    isAvailable() {
        let storage
        try {
            storage = window[this.type]
            let x = '__storage_test__'
            storage.setItem(x, x)
            storage.removeItem(x)
            return true
        } catch(e) {
            return e instanceof DOMException && (
                // everything except Firefox
                e.code === 22 ||
                // Firefox
                e.code === 1014 ||
                // test name field too, because code might not be present everything except Firefox
                e.name === 'QuotaExceededError' ||
                // Firefox
                e.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
                // acknowledge QuotaExceededError only if there's something already stored
                (storage && storage.length !== 0)
        }
    }

    set del(key) {
        this.remove(key)
    }

    remove(key) {
        if (Array.isArray(key)) {
            key.forEach((elm) => {
                this.storage.removeItem(elm)
            })
        }
        if (typeof key === 'string') {
            this.storage.removeItem(key)
        }
    }

    removeOf(regex) {
        let re = new RegExp(regex, 'i'),
            removeKeys = []

        for (let i = 0; i < this.storage.length; i++) {
            if (re.test(this.storage.key(i))) {
                removeKeys.push(this.storage.key(i))
            }
        }
        removeKeys.forEach((_key) => {
            this.storage.removeItem(_key)
        })
    }

    clear() {
        this.storage.clear()
    }

    has(key) {
        let isMatch = false
        for (let i = 0; i < this.storage.length; i++) {
            if (key === this.storage.key(i)) {
                isMatch = true
                break
            }
        }
        return isMatch
    }

    load(key) {
        let value = this.storage.getItem(key)
        if (value) {
            if (this.obfuscation) {
                value = this.deobfuscation(value)
            }
            if (/^(\{|\[).*(\}|\])$/.test(value)) {
                value = JSON.parse(value)
            }
            if (/^(true|false)$/i.test(value)) {
                value = value.toLowerCase() === 'false' ? false : !!value
            }
        } else {
            //throw new Error('No matching data was found.')
            console.error(`No data was found that matches the "${key}".`)
        }
        return value
    }

    save(key, value) {
        if (!key.toString() || !value.toString()) {
            throw new Error('Not enough arguments for the data to save.')
        }
        key = key.toString()
        if (typeof value === 'object') {
            value = JSON.stringify(value)
        }
        if (this.obfuscation) {
            value = this.toObfuscation(value)
        }
        this.storage.setItem(key, value)
    }

    /**
     * Obfuscate a plaintext string with a simple rotation algorithm similar to the rot13 cipher.
     */
    toObfuscation(value) {
        let chars = value.toString().split(''),
            rot   = 13,
            max   = 126
        
        for (let i = 0; i < chars.length; i++) {
            let c = chars[i].charCodeAt(0)
            if (c <= max) {
                chars[i] = String.fromCharCode((chars[i].charCodeAt(0) + rot) % max)
            }
        }
        let obfsValue = chars.join(''),
            utf8str   = String.fromCharCode.apply(null, new TextEncoder().encode(obfsValue))
        return btoa(utf8str)
    }

    /**
     * De-obfuscate an obfuscated string with the method above.
     */
    deobfuscation(value) {
        let decoded_utf8str = atob(value.toString()),
            decoded_array   = new Uint8Array(Array.prototype.map.call(decoded_utf8str, (c) => c.charCodeAt())),
            decoded         = new TextDecoder().decode(decoded_array),
            chars = decoded.split(''),
            rot   = 113,//126 - 13
            max   = 126
        
        for (let i = 0; i < chars.length; i++) {
            let c = chars[i].charCodeAt(0)
            if (c <= max) {
                chars[i] = String.fromCharCode((chars[i].charCodeAt(0) + rot) % max)
            }
        }
        return chars.join('')
    }

}