// app.js: makes the dashboard update without reloading the page.

const messageBox = document.getElementById('message');

// 1250 -> "R1,250.00", the same format PHP's number_format() gives
function formatRand(amount) {
    return 'R' + Number(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

// Put fresh numbers into the "What everyone owes" table
function updateTotals(totals) {
    for (const client of totals) {
        const row = document.querySelector(`tr[data-client-id="${client.id}"]`);
        if (!row) continue;   // a client added since the page loaded; skip them
        row.querySelector('.count').textContent = client.trip_count;
        row.querySelector('.owed').textContent = formatRand(client.total_owed);
    }
}

// Show a green note, or a red one for errors.
// If a tripId is given, add an Undo button that deletes that trip.
function showMessage(text, isError = false, tripId = null) {
    messageBox.textContent = text;
    messageBox.style.background = isError ? '#ffe6e6' : '#e6ffe6';
    messageBox.hidden = false;

    if (tripId !== null) {
        const undoButton = document.createElement('button');
        undoButton.textContent = 'Undo';
        undoButton.style.marginLeft = '8px';
        undoButton.addEventListener('click', () => undoTrip(tripId, undoButton));
        messageBox.append(undoButton);
    }
}

// Delete a trip that was just added, then refresh the totals
async function undoTrip(tripId, undoButton) {
    undoButton.disabled = true;

    const formData = new FormData();
    formData.append('trip_id', tripId);

    try {
        const data = await postToServer('delete_trip.php', formData);
        updateTotals(data.totals);
        showMessage('Undone. That trip was removed.');
    } catch (error) {
        showMessage(error.message, true);
    }
}

// Send form data to a PHP file and get its JSON reply.
// Throws an Error (with PHP's message) if anything went wrong.
async function postToServer(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: formData,
    });

    let data;
    try {
        data = await response.json();
    } catch {
        // PHP crashed and sent an HTML error page instead of JSON
        throw new Error('Something went wrong on the server (status ' + response.status + ').');
    }

    if (!data.ok) {
        throw new Error(data.error);
    }
    return data;
}

// Take over every trip form on the page
for (const form of document.querySelectorAll('form.trip-form')) {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();   // stop the normal "go to add_trip.php" page load

        const button = form.querySelector('button');
        button.disabled = true;   // stops a double-click logging the trip twice

        try {
            const data = await postToServer(form.action, new FormData(form));
            updateTotals(data.totals);
            showMessage(data.message, false, data.tripId);

            // Clear the one-time form, ready for the next trip
            if (!form.querySelector('[name="bookmark_id"]')) {
                form.reset();
            }
        } catch (error) {
            showMessage(error.message, true);
        } finally {
            button.disabled = false;   // runs whether it worked or not
        }
    });
}