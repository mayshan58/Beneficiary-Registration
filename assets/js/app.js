/**
 * NGO Beneficiary Registration System
 * Client-Side Application Scripts
 */

// South Sudan States & Counties Dataset for instant client dropdown sync
const SS_LOCATIONS = {
    "Central Equatoria": ["Juba", "Yei", "Morobo", "Lainya", "Kajo-Keji", "Terekeka"],
    "Eastern Equatoria": ["Torit", "Kapoeta East", "Kapoeta North", "Kapoeta South", "Magwi", "Ikotos", "Budi", "Lafon"],
    "Western Equatoria": ["Yambio", "Nzara", "Maridi", "Mundri West", "Mundri East", "Ibba", "Ezo", "Nagero", "Tambura", "Mvolo"],
    "Jonglei": ["Bor", "Akobo", "Ayod", "Duk", "Fangak", "Nyirol", "Pigi", "Twic East", "Uror"],
    "Unity": ["Rubkona (Bentiu)", "Guit", "Koch", "Leer", "Mayendit", "Mayom", "Pariang", "Panyijiar"],
    "Upper Nile": ["Malakal", "Renk", "Manyo", "Fashoda", "Maban", "Maiwut", "Luakpiny/Nasir", "Longochuk", "Melut", "Panyikang", "Ulang"],
    "Lakes": ["Rumbek Centre", "Rumbek East", "Rumbek North", "Wulu", "Cueibet", "Yirol West", "Yirol East", "Awerial"],
    "Warrap": ["Kuajok", "Gogrial West", "Gogrial East", "Tonj North", "Tonj East", "Tonj South", "Twic"],
    "Western Bahr el Ghazal": ["Wau", "Raja", "Jur River"],
    "Northern Bahr el Ghazal": ["Aweil Centre", "Aweil East", "Aweil North", "Aweil South", "Aweil West"],
    "Abyei Administrative Area": ["Abyei"],
    "Greater Pibor Administrative Area": ["Pibor", "Pochalla"],
    "Ruweng Administrative Area": ["Pariang", "Abiemnhom"]
};

document.addEventListener("DOMContentLoaded", function () {
    // 1. Dynamic State -> County Dropdown Handler
    const stateSelect = document.getElementById("state_select");
    const countySelect = document.getElementById("county_select");

    if (stateSelect && countySelect) {
        stateSelect.addEventListener("change", function () {
            const selectedState = this.value;
            const currentCounty = countySelect.getAttribute("data-selected") || "";
            
            countySelect.innerHTML = '<option value="">-- Select County --</option>';
            
            if (SS_LOCATIONS[selectedState]) {
                SS_LOCATIONS[selectedState].forEach(function (county) {
                    const opt = document.createElement("option");
                    opt.value = county;
                    opt.textContent = county;
                    if (county === currentCounty) {
                        opt.selected = true;
                    }
                    countySelect.appendChild(opt);
                });
            }
        });

        // Trigger change on initial load if a state is already selected
        if (stateSelect.value) {
            const currentCounty = countySelect.getAttribute("data-selected") || "";
            countySelect.innerHTML = '<option value="">-- Select County --</option>';
            if (SS_LOCATIONS[stateSelect.value]) {
                SS_LOCATIONS[stateSelect.value].forEach(function (county) {
                    const opt = document.createElement("option");
                    opt.value = county;
                    opt.textContent = county;
                    if (county === currentCounty) {
                        opt.selected = true;
                    }
                    countySelect.appendChild(opt);
                });
            }
        }
    }

    // 2. Real-Time Duplicate Check Handler (Debounced)
    const nameInput = document.getElementById("full_name");
    const dobInput = document.getElementById("date_of_birth");
    const phoneInput = document.getElementById("phone");
    const idDocInput = document.getElementById("id_document_number");
    const dupAlertContainer = document.getElementById("duplicate_alert_container");
    const excludeIdInput = document.getElementById("beneficiary_id_exclude");

    if (nameInput && dupAlertContainer) {
        let debounceTimer = null;

        function checkDuplicatesLive() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                const nameVal = nameInput.value.trim();
                const dobVal = dobInput ? dobInput.value : "";
                const phoneVal = phoneInput ? phoneInput.value.trim() : "";
                const docVal = idDocInput ? idDocInput.value.trim() : "";
                const stateVal = stateSelect ? stateSelect.value : "";
                const countyVal = countySelect ? countySelect.value : "";
                const excludeId = excludeIdInput ? excludeIdInput.value : "";

                // Require at least 3 characters in name or a phone/ID to query
                if (nameVal.length < 3 && phoneVal.length < 5 && docVal.length < 3) {
                    dupAlertContainer.innerHTML = "";
                    return;
                }

                const checkUrl = (window.APP_BASE_URL || "../") + "beneficiaries/check_duplicate.php";
                const formData = new FormData();
                formData.append("full_name", nameVal);
                formData.append("date_of_birth", dobVal);
                formData.append("phone", phoneVal);
                formData.append("id_document_number", docVal);
                formData.append("state", stateVal);
                formData.append("county", countyVal);
                if (excludeId) {
                    formData.append("exclude_id", excludeId);
                }

                fetch(checkUrl, {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data && data.has_duplicates && data.matches.length > 0) {
                        let html = `
                            <div class="alert alert-warning border-warning shadow-sm animate__animated animate__fadeIn">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-exclamation-triangle-fill text-warning fs-4 me-2"></i>
                                    <strong>Potential Duplicate Registration Warning (${data.matches.length} Match Found)</strong>
                                </div>
                                <p class="small text-muted mb-2">The system detected existing beneficiaries matching these details. Please verify to avoid creating duplicate records:</p>
                                <ul class="list-group list-group-flush small mb-2">
                        `;
                        data.matches.forEach(m => {
                            html += `
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center py-2 px-0">
                                    <div>
                                        <strong>${escapeHtml(m.record.full_name)}</strong> 
                                        <span class="badge bg-secondary ms-1">${escapeHtml(m.record.beneficiary_id)}</span>
                                        <br>
                                        <span class="text-muted">DOB: ${m.record.date_of_birth || 'N/A'} | Location: ${m.record.county}, ${m.record.state}</span>
                                        <div class="text-danger small mt-1"><i class="bi bi-info-circle me-1"></i>${escapeHtml(m.match_type)} (${m.confidence}% match confidence)</div>
                                    </div>
                                    <a href="${window.APP_BASE_URL || '../'}beneficiaries/view.php?id=${m.record.id}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        View Profile <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </li>
                            `;
                        });
                        html += `
                                </ul>
                                <div class="small text-muted fst-italic">
                                    Note: You may still proceed if this is a distinct beneficiary with similar names.
                                </div>
                            </div>
                        `;
                        dupAlertContainer.innerHTML = html;
                    } else {
                        dupAlertContainer.innerHTML = "";
                    }
                })
                .catch(err => {
                    console.error("Duplicate check error:", err);
                });
            }, 500);
        }

        nameInput.addEventListener("input", checkDuplicatesLive);
        if (dobInput) dobInput.addEventListener("change", checkDuplicatesLive);
        if (phoneInput) phoneInput.addEventListener("input", checkDuplicatesLive);
        if (idDocInput) idDocInput.addEventListener("input", checkDuplicatesLive);
        if (countySelect) countySelect.addEventListener("change", checkDuplicatesLive);
    }
});

// Helper for escaping HTML strings
function escapeHtml(text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}

