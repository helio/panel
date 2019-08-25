$(document).ready(function () {
    // current state of log viewer
    const state = {
        cursor: {
            // first cursor is used for fetching entries newer than currently displayed
            first: null,
            // last cursor is used for fetching entries older than oldest currently displayed entry
            last: null
        },
        // flag whether to load more older log entries when scrolling down
        loadOlder: true,
        // flag whether to load newer log entries. When set to a number it's the interval in seconds.
        loadNewer: false
    }

    const fetchOptions = {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json'
        }
    }

    function fetchLogs(location, qs) {
        const url = new URL(`${window.location.origin}${location}`)
        const searchParams = new URLSearchParams(url.search)
        Object.keys(qs).forEach(k => qs[k] && searchParams.set(k, qs[k]))

        url.search = searchParams.toString()
        return fetch(`${url}`, fetchOptions)
            .then(response => response.json())
            .then(({ logs, cursor }) => ({ logs, cursor }))
    }

    /**
     * Converts HTML string to element.
     *
     * Requires <template> functionality in browser.
     *
     * @param {String} html representing a single element
     * @return {ChildNode}
     */
    function htmlToElement(html) {
        var template = document.createElement('template');
        html = html.trim(); // Never return a text node of whitespace as the result
        template.innerHTML = html;
        return template.content.firstChild;
    }

    function formatDate (timestamp) {
        return new Date(timestamp).toLocaleString()
    }

    /**
     * Updates the list of log entries with new items.
     *
     * @param $el
     * @param logEntries
     * @param position
     */
    function updateList($el, logEntries, position = 'beforeend') {
        const htmlItems = logEntries.map(({ timestamp, message, source, executionId }) => {
            return htmlToElement(`
<tr>
    <td>${formatDate(timestamp)}</td>
    <td class="helio-monospace ww-break-word">${message}</td>
    <td>${source}</td>
    <td>${executionId}</td>
</tr>`);
        })
        for (let i = 0; i < htmlItems.length; i++) {
            $el.insertAdjacentElement(position, htmlItems[i]);
        }
    }


    const $scrollContainer = document.querySelector('html');
    const $logContainer = document.querySelector('#helio-job-logs');
    const $logMessageContainer = $logContainer.querySelector('tbody');
    const url = $logContainer.dataset.fetchUrl;

    /**
     * loadOlder loads older log entries than what's currently displayed.
     */
    function loadOlder() {
        if (!state.loadOlder) {
            return
        }
        state.loadOlder = false

        fetchLogs(url, { cursor: state.cursor.last, size: 100, sort: 'desc' })
            .then(({ logs, cursor }) => {
                if (logs.length && cursor.last) {
                    state.cursor.last = cursor.last
                    state.loadOlder = true
                } else {
                    // if there is no log elements and no last cursor, we reached the end and can disable loading more.
                    state.loadOlder = false;
                }
                // loadOlder gets called first, so initialize `first` cursor as well (which is used in loadNewer only).
                if (!state.cursor.first && cursor.first) {
                    state.cursor.first = cursor.first;
                }

                updateList($logMessageContainer, logs);
            });
    }

    /**
     * loadNewer loads newer log entries than what's currently displayed.
     * If state.loadNewer is an integer > 0, will trigger loadNewer() again to refresh automatically.
     */
    function loadNewer() {
        // sorts `asc` because the cursor uses `search_after` functionality of ES, which requires reversing order if you want to
        // search for newer log entries (before cursor).
        fetchLogs(url, { cursor: state.cursor.first, size: 100, sort: 'asc' })
            .then(({ logs, cursor }) => {
                // last cursor is (because `asc` sorting) the newest entry
                if (logs.length && cursor.last) {
                    state.cursor.first = cursor.last
                }

                // `afterbegin` inserts elements in the list at the top. That's why we don't need to reverse the list
                // because on iteration the newest log message always gets pushed to the top again.
                updateList($logMessageContainer, logs, 'afterbegin');

                if (state.loadNewer > 0) {
                    setTimeout(loadNewer, state.loadNewer*1000);
                }
            });
    }

    const $form = document.querySelector('#helio-job-logs-form');
    $form.addEventListener('submit', (e) => {
        e.preventDefault();

        const data = new FormData($form);
        state.loadNewer = data.get('interval')
        if (state.loadNewer === '') {
            state.loadNewer = 0;
        }
        if (state.loadNewer > 0) {
            loadNewer()
        }
    })

    loadOlder();

    const buffer = $scrollContainer.scrollHeight / 3
    window.addEventListener('scroll', () => {
        if (state.loadOlder && $scrollContainer.scrollTop + $scrollContainer.clientHeight >= ($scrollContainer.scrollHeight - buffer)) {
            loadOlder()
        }
    }, { passive: true })

});
