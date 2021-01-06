/**
 * Asynchronously post as a wrapper for the Fetch API
 * @param  {string} [url='']  - URL of the request destination
 * @param  {Object} [data={}] - The key-value type object of data to send
 * @param  {string} [datatype='json'] - Response data type (defaults to JSON)
 * @param  {number} [timeout=15000] - Set timeout in fetching (defaults to after 15 sec)
 * @return {Object} The response of fetch request is returned as a promise object
 */
async function postData( url = '', data = {}, datatype = 'json', timeout = 15000 ) {
    const controller = new AbortController(),
          timeoutId  = setTimeout(() => { controller.abort() }, timeout )
    let params  = new URLSearchParams()

    if ( data ) {
        for ( let key in data ) {
            if ( Object.prototype.hasOwnProperty.call( data, key ) ) {
                params.append( key, data[key] )
            }
        }
    }
    try {
        const response = await fetch( url, {
            method: 'POST',
            mode: 'cors',
            cache: 'default',
            credentials: 'same-origin',
            //headers: { 'Content-Type': 'application/json' },
            redirect: 'follow',
            referrerPolicy: 'no-referrer-when-downgrade',
            signal: controller.signal,
            //body: JSON.stringify( data ),
            body: params,
        })
        if ( ! response.ok ) {
            const desc = `status code: ${response.status}, text: ${response.statusText}`
            throw new Error(desc)
        }
        return datatype === 'json' ? await response.json() : await response.text()
    } finally {
        clearTimeout( timeoutId )
    }
}

/**
 * Generate hidden field for form
 * @param  {!string} - Name attribute of input tag
 * @param  {!string} - Value attribute of input tag
 * @return {Object} DOM Object of input tag
 */
function addPostField( name, value ) {
    let newField = document.createElement( 'input' )
    newField.setAttribute( 'type', 'hidden' )
    newField.setAttribute( 'name', name )
    newField.setAttribute( 'value', value )
    return newField
}

export { postData, addPostField }
