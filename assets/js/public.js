(function() {
	'use strict';

	const LTB = {
		container: null,
		currentStep: 1,
		playerCount: ltbData.minPlayers || 1,
		selectedGameMode: null,
		selectedDuration: 1, // Standard: 1 Stunde
		currentDate: null,
		availableSlots: [],
		cart: [],

		init: function() {
			this.container = document.querySelector('.ltb-booking-container');
			if (!this.container) {
				return;
			}

			// Spieleranzahl initialisieren - IMMER Mindestanzahl verwenden
			const minPlayers = parseInt(ltbData.minPlayers) || 1;
			this.playerCount = minPlayers; // IMMER die Mindestanzahl verwenden
			const countEl = this.container.querySelector('#ltb-player-count');
			if (countEl) {
				// HTML-Wert ignorieren und IMMER die Mindestanzahl setzen
				countEl.textContent = this.playerCount.toString();
			}

			this.currentDate = new Date();
			this.bindEvents();
			this.updatePlayerButtons();
			this.updateInquiryRequirement();
			this.updateCart();
			this.updateDateDisplay();
		},

		bindEvents: function() {
			// Spieleranzahl
			const minusBtn = this.container.querySelector('.ltb-btn-minus');
			const plusBtn = this.container.querySelector('.ltb-btn-plus');
			if (minusBtn) minusBtn.addEventListener('click', () => this.changePlayerCount(-1));
			if (plusBtn) plusBtn.addEventListener('click', () => this.changePlayerCount(1));

			// Schritt-Navigation
			const nextBtns = this.container.querySelectorAll('.ltb-next-step');
			nextBtns.forEach(btn => {
				btn.addEventListener('click', (e) => {
					const nextStep = parseInt(e.target.dataset.next);
					this.goToStep(nextStep);
				});
			});

			const prevBtns = this.container.querySelectorAll('.ltb-prev-step');
			prevBtns.forEach(btn => {
				btn.addEventListener('click', (e) => {
					const prevStep = parseInt(e.target.dataset.prev);
					this.goToStep(prevStep);
				});
			});

			// Spielmodus-Auswahl
			const modeCards = this.container.querySelectorAll('.ltb-game-mode-card');
			modeCards.forEach(card => {
				card.addEventListener('click', () => this.selectGameMode(card));
			});

			// Buchungsdauer-Auswahl
			const durationBtns = this.container.querySelectorAll('.ltb-duration-btn');
			durationBtns.forEach(btn => {
				btn.addEventListener('click', (e) => {
					e.preventDefault();
					e.stopPropagation();
					console.log('Dauer-Button geklickt:', btn.dataset.duration);
					this.selectDuration(btn);
				});
			});
			
			// Package-Cards (Paket-Auswahl) - DIREKT beim Start binden
			const packageCards = this.container.querySelectorAll('.ltb-package-card');
			console.log('Package-Cards gefunden:', packageCards.length);
			packageCards.forEach(card => {
				// Click-Event
				card.addEventListener('click', (e) => {
					e.preventDefault();
					e.stopPropagation();
					console.log('Package-Card CLICK:', card.dataset.duration);
					this.selectPackage(card);
				});
				// Touch-Event für mobile Geräte
				card.addEventListener('touchend', (e) => {
					e.preventDefault();
					console.log('Package-Card TOUCH:', card.dataset.duration);
					this.selectPackage(card);
				});
			});

			// Datum-Navigation
			const prevDateBtn = this.container.querySelector('.ltb-prev-date');
			const nextDateBtn = this.container.querySelector('.ltb-next-date');
			if (prevDateBtn) prevDateBtn.addEventListener('click', () => this.navigateDate(-1));
			if (nextDateBtn) nextDateBtn.addEventListener('click', () => this.navigateDate(1));
			
			// Kalender-Datepicker (jetzt sichtbares Input)
			const dateInput = this.container.querySelector('.ltb-date-input');
			if (dateInput) {
				// Minimum-Datum auf heute setzen
				const today = new Date();
				dateInput.min = this.formatDate(today);
				// Aktuelles Datum setzen
				dateInput.value = this.formatDate(this.currentDate);
				
				// Bei Änderung Datum übernehmen
				dateInput.addEventListener('change', (e) => {
					const selectedDate = new Date(e.target.value + 'T12:00:00');
					if (!isNaN(selectedDate.getTime())) {
						this.currentDate = selectedDate;
						this.updateDateDisplay();
						// Wenn wir bereits in Schritt 4 sind, Slots neu laden
						if (this.currentStep === 4) {
							this.loadTimeSlots();
						}
						console.log('Datum per Kalender gewählt:', this.formatDate(this.currentDate));
					}
				});
			}

			// Anderes Datum wählen Button
			const selectAnotherDateBtn = this.container.querySelector('.ltb-select-another-date');
			if (selectAnotherDateBtn) {
				selectAnotherDateBtn.addEventListener('click', () => this.goToStep(4));
			}

			// Warenkorb
			const checkoutBtn = this.container.querySelector('.ltb-btn-checkout');
			console.log('Checkout-Button gefunden:', checkoutBtn);
			if (checkoutBtn) {
				checkoutBtn.addEventListener('click', () => {
					console.log('Checkout-Button geklickt!');
					this.showCheckout();
				});
			} else {
				console.error('Checkout-Button NICHT gefunden!');
			}


			// Checkout-Formular
			const checkoutForm = this.container.querySelector('#ltb-checkout-form');
			if (checkoutForm) {
				checkoutForm.addEventListener('submit', (e) => this.handleCheckout(e));
			}

			const modalClose = this.container.querySelector('.ltb-modal-close');
			const modalCancel = this.container.querySelector('.ltb-modal-cancel');
			if (modalClose) modalClose.addEventListener('click', () => this.hideCheckout());
			if (modalCancel) modalCancel.addEventListener('click', () => this.hideCheckout());
		},

		changePlayerCount: function(delta) {
			const minPlayers = parseInt(ltbData.minPlayers) || 1;
			const maxPlayers = parseInt(ltbData.maxPlayers) || 0; // 0 = keine Beschränkung
			
			// Sicherstellen, dass playerCount eine Zahl ist
			let currentCount = parseInt(this.playerCount) || minPlayers;
			
			// Delta als Zahl addieren/subtrahieren
			let newCount = currentCount + parseInt(delta);
			
			// Limits prüfen
			if (newCount < minPlayers) {
				newCount = minPlayers;
				this.showMessage('info', 'Mindestanzahl: ' + minPlayers + ' Spieler');
			} else if (maxPlayers > 0 && newCount > maxPlayers) {
				newCount = maxPlayers;
				this.showMessage('info', 'Maximalanzahl: ' + maxPlayers + ' Spieler');
			}
			
			// Sicherstellen, dass die Anzahl nicht negativ wird
			if (newCount < 1) {
				newCount = 1;
			}
			
			// Als Zahl speichern
			this.playerCount = parseInt(newCount);
			const countEl = this.container.querySelector('#ltb-player-count');
			if (countEl) countEl.textContent = this.playerCount.toString();
			this.updatePlayerButtons();
			this.updateInquiryRequirement();
			this.updateCart();
		},

		updatePlayerButtons: function() {
			const minPlayers = parseInt(ltbData.minPlayers) || 1;
			const maxPlayers = parseInt(ltbData.maxPlayers) || 0; // 0 = keine Beschränkung
			
			// Sicherstellen, dass playerCount eine Zahl ist
			const currentCount = parseInt(this.playerCount) || minPlayers;
			
			const minusBtn = this.container.querySelector('.ltb-btn-minus');
			const plusBtn = this.container.querySelector('.ltb-btn-plus');
			
			if (minusBtn) {
				minusBtn.disabled = (currentCount <= minPlayers);
			}
			if (plusBtn) {
				// Wenn maxPlayers = 0, dann keine Beschränkung (Button immer aktiv)
				plusBtn.disabled = (maxPlayers > 0 && currentCount >= maxPlayers);
			}
		},

		updateInquiryRequirement: function() {
			const inquiryThreshold = parseInt(ltbData.inquiryThreshold) || 0; // 0 = keine Anfrage-Pflicht
			const currentCount = parseInt(this.playerCount) || 1;
			const messageField = this.container.querySelector('#ltb-checkout-message');
			const messageLabel = this.container.querySelector('label[for="ltb-checkout-message"]');
			
			if (inquiryThreshold > 0 && currentCount >= inquiryThreshold) {
				// Anfrage erforderlich
				if (messageField) {
					messageField.required = true;
					messageField.setAttribute('aria-required', 'true');
				}
				if (messageLabel) {
					const requiredSpan = messageLabel.querySelector('.required');
					if (!requiredSpan) {
						messageLabel.innerHTML = messageLabel.textContent + ' <span class="required">*</span>';
					}
				}
				// Info-Banner anzeigen
				this.showInquiryInfo();
			} else {
				// Keine Anfrage erforderlich
				if (messageField) {
					messageField.required = false;
					messageField.removeAttribute('aria-required');
				}
				if (messageLabel) {
					const requiredSpan = messageLabel.querySelector('.required');
					if (requiredSpan) {
						requiredSpan.remove();
					}
				}
				// Info-Banner verstecken
				this.hideInquiryInfo();
			}
		},

		showInquiryInfo: function() {
			let infoBanner = this.container.querySelector('.ltb-inquiry-info');
			if (!infoBanner) {
				infoBanner = document.createElement('div');
				infoBanner.className = 'ltb-inquiry-info';
				infoBanner.innerHTML = '<p>' + (ltbData.strings.inquiryRequired || 'Bei dieser Spieleranzahl benötigen wir weitere Details. Bitte füllen Sie das Nachrichtenfeld aus.') + '</p>';
				
				// Vor dem Checkout-Formular einfügen
				const checkoutForm = this.container.querySelector('#ltb-checkout-form');
				if (checkoutForm) {
					checkoutForm.insertBefore(infoBanner, checkoutForm.firstChild);
				}
			}
			infoBanner.style.display = 'block';
		},

		hideInquiryInfo: function() {
			const infoBanner = this.container.querySelector('.ltb-inquiry-info');
			if (infoBanner) {
				infoBanner.style.display = 'none';
			}
		},

		goToStep: function(step) {
			console.log('goToStep aufgerufen mit Schritt:', step, 'Aktueller Schritt:', this.currentStep);
			
			// Validierung vor Schrittwechsel
			if (step === 2) {
				const minPlayers = parseInt(ltbData.minPlayers) || 1;
				const maxPlayers = parseInt(ltbData.maxPlayers) || 0; // 0 = keine Beschränkung
				
				// Sicherstellen, dass playerCount eine Zahl ist
				const currentCount = parseInt(this.playerCount) || minPlayers;
				this.playerCount = currentCount;
				
				if (currentCount < minPlayers) {
					this.showMessage('error', 'Mindestanzahl: ' + minPlayers + ' Spieler');
					return;
				}
				if (maxPlayers > 0 && currentCount > maxPlayers) {
					this.showMessage('error', 'Maximalanzahl: ' + maxPlayers + ' Spieler');
					return;
				}
			}
			if (step === 3 && !this.selectedDuration) {
				this.showMessage('error', 'Bitte wählen Sie ein Paket.');
				return;
			}
			if (step === 4 && !this.currentDate) {
				this.showMessage('error', 'Bitte wählen Sie ein Datum.');
				return;
			}

			// Alle Schritte verstecken
			const allSteps = this.container.querySelectorAll('.ltb-step');
			allSteps.forEach(s => {
				s.style.display = 'none';
				console.log('Verstecke Schritt:', s.dataset.step);
			});

			// Gewünschten Schritt anzeigen
			const targetStep = this.container.querySelector('.ltb-step-' + step);
			if (targetStep) {
				targetStep.style.display = 'block';
				this.currentStep = step;
				console.log('Zeige Schritt:', step, 'Element:', targetStep);
				
				// *** AUTOMATISCH NACH OBEN SCROLLEN ***
				// Zum Anfang des Booking-Containers scrollen
				setTimeout(() => {
					this.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}, 50);

				// Spezielle Aktionen pro Schritt
				if (step === 2) {
					// Spielmodus automatisch auf "LaserTag" setzen
					this.selectedGameMode = 'LaserTag';
					console.log('Schritt 2 aktiv - Paket-Auswahl');
				} else if (step === 3) {
					this.updateDateDisplay();
				} else if (step === 4) {
					console.log('Wechsel zu Schritt 4 - lade Zeitslots...');
					// Kurze Verzögerung, damit das DOM aktualisiert wird
					setTimeout(() => {
						this.loadTimeSlots();
					}, 100);
				}
			} else {
				console.error('Schritt', step, 'nicht gefunden!');
			}
		},

		selectPackage: function(card) {
			console.log('selectPackage aufgerufen mit Card:', card, 'Dauer:', card.dataset.duration);
			
			// Alle Cards deselektieren
			const allCards = this.container.querySelectorAll('.ltb-package-card');
			allCards.forEach(c => c.classList.remove('ltb-selected'));
			
			// Ausgewählte Card markieren
			card.classList.add('ltb-selected');
			this.selectedDuration = parseInt(card.dataset.duration) || 1;
			
			// Spielmodus automatisch setzen
			this.selectedGameMode = 'LaserTag';
			
			console.log('Ausgewähltes Paket - Dauer:', this.selectedDuration);
			
			// Weiter-Button anzeigen
			const nextBtn = this.container.querySelector('.ltb-step-2 .ltb-next-step');
			if (nextBtn) {
				nextBtn.style.display = 'inline-block';
				console.log('Weiter-Button angezeigt');
			} else {
				console.warn('Weiter-Button nicht gefunden!');
			}
		},

		selectDuration: function(btn) {
			console.log('selectDuration aufgerufen mit Button:', btn, 'Dauer:', btn.dataset.duration);
			
			// Alle Buttons deselektieren
			const allBtns = this.container.querySelectorAll('.ltb-duration-btn');
			allBtns.forEach(b => b.classList.remove('ltb-selected'));
			
			// Ausgewählten Button markieren
			btn.classList.add('ltb-selected');
			this.selectedDuration = parseInt(btn.dataset.duration) || 1;
			
			console.log('Ausgewählte Dauer:', this.selectedDuration);
			
			// Weiter-Button anzeigen
			const nextBtn = this.container.querySelector('.ltb-step-2 .ltb-next-step');
			if (nextBtn) {
				nextBtn.style.display = 'inline-block';
				console.log('Weiter-Button angezeigt');
			} else {
				console.warn('Weiter-Button nicht gefunden!');
			}
		},

		selectGameMode: function(card) {
			// Alle Karten deselektieren
			const allCards = this.container.querySelectorAll('.ltb-game-mode-card');
			allCards.forEach(c => {
				c.classList.remove('ltb-selected');
				const btn = c.querySelector('.ltb-mode-select-btn');
				if (btn) {
					btn.textContent = 'Auswählen';
					btn.classList.remove('ltb-selected');
				}
			});

			// Ausgewählte Karte markieren
			card.classList.add('ltb-selected');
			const btn = card.querySelector('.ltb-mode-select-btn');
			if (btn) {
				btn.textContent = 'Ausgewählt';
				btn.classList.add('ltb-selected');
			}

			this.selectedGameMode = card.dataset.mode;

			// Weiter-Button anzeigen
			const nextBtn = this.container.querySelector('.ltb-step-2 .ltb-next-step');
			if (nextBtn) nextBtn.style.display = 'inline-block';
		},

		navigateDate: function(delta) {
			this.currentDate.setDate(this.currentDate.getDate() + delta);
			this.updateDateDisplay();
			if (this.currentStep === 4) {
				this.loadTimeSlots();
			}
		},

		updateDateDisplay: function() {
			// Date-Input synchronisieren
			const dateInput = this.container.querySelector('.ltb-date-input');
			if (dateInput) {
				dateInput.value = this.formatDate(this.currentDate);
			}
		},

		loadTimeSlots: function() {
			const container = this.container.querySelector('.ltb-time-slots-container');
			if (!container) {
				console.error('Container nicht gefunden!');
				return;
			}
			
			const loading = container.querySelector('.ltb-calendar-loading');
			const grid = container.querySelector('.ltb-time-slots-grid');

			if (loading) loading.style.display = 'block';
			if (grid) grid.innerHTML = '';

			const dateStr = this.formatDate(this.currentDate);
			const month = this.currentDate.getMonth() + 1;
			const year = this.currentDate.getFullYear();

			console.log('Lade Slots für:', dateStr, 'Monat:', month, 'Jahr:', year);

			const formData = new FormData();
			formData.append('action', 'ltb_get_available_slots');
			formData.append('nonce', ltbData.nonce);
			formData.append('month', month);
			formData.append('year', year);

			console.log('Sende AJAX-Request...', {
				action: 'ltb_get_available_slots',
				url: ltbData.ajaxUrl,
				month: month,
				year: year
			});

			fetch(ltbData.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
			.then(response => {
				console.log('Response Status:', response.status);
				if (!response.ok) {
					throw new Error('HTTP ' + response.status + ': ' + response.statusText);
				}
				return response.json();
			})
			.then(data => {
				console.log('Response Data:', data);
				if (loading) loading.style.display = 'none';
				if (data.success) {
					console.log('Alle Slots vom Server:', data.data);
					if (Array.isArray(data.data)) {
						this.availableSlots = data.data.filter(slot => slot.date === dateStr);
						console.log('Gefilterte Slots für', dateStr, ':', this.availableSlots);
						if (this.availableSlots.length === 0) {
							console.warn('Keine Slots gefunden für Datum:', dateStr);
							if (data.data.length > 0) {
								console.warn('Verfügbare Daten in Slots:', data.data.map(s => s.date));
							} else {
								console.warn('Keine Slots vom Server erhalten');
							}
						}
						this.renderTimeSlots();
					} else {
						console.error('Ungültige Datenstruktur:', data.data);
						this.showMessage('error', 'Ungültige Antwort vom Server.');
					}
				} else {
					console.error('Fehler beim Laden der Slots:', data);
					const errorMsg = data.data?.message || data.data?.error || data.data || 'Unbekannter Fehler';
					this.showMessage('error', 'Fehler beim Laden der Zeiten: ' + errorMsg);
				}
			})
			.catch(error => {
				if (loading) loading.style.display = 'none';
				console.error('AJAX Fehler:', error);
				console.error('Fehler-Details:', {
					message: error.message,
					stack: error.stack
				});
				this.showMessage('error', 'Fehler beim Laden der Zeiten: ' + (error.message || 'Netzwerkfehler'));
			});
		},

		renderTimeSlots: function() {
			const grid = this.container.querySelector('.ltb-time-slots-grid');
			if (!grid) {
				console.error('Grid nicht gefunden! Aktueller Schritt:', this.currentStep);
				return;
			}

			// Prüfen ob Schritt 4 wirklich sichtbar ist
			const step4 = this.container.querySelector('.ltb-step-4');
			if (!step4 || step4.style.display === 'none') {
				console.warn('Schritt 4 ist nicht sichtbar!');
			}

			grid.innerHTML = '';

			console.log('Rendere', this.availableSlots.length, 'Slots in Grid:', grid);

			if (this.availableSlots.length === 0) {
				grid.innerHTML = '<p style="color: var(--ltb-text-light); padding: 2rem; text-align: center;">Keine verfügbaren Zeiten für dieses Datum.</p>';
				return;
			}

			// Prüfen ob Spielmodus ausgewählt ist
			if (!this.selectedGameMode) {
				grid.innerHTML = '<p style="color: var(--ltb-text-light); padding: 2rem; text-align: center;">Bitte wählen Sie zuerst einen Spielmodus.</p>';
				return;
			}

			// *** SLOTS NACH UHRZEIT SORTIEREN ***
			this.availableSlots.sort((a, b) => {
				const hourA = parseInt(a.hour) || 0;
				const hourB = parseInt(b.hour) || 0;
				return hourA - hourB;
			});
			console.log('Slots sortiert nach Uhrzeit');

			// *** PERFORMANCE: Preis direkt berechnen statt AJAX pro Slot ***
			// Preis basiert auf gewähltem Paket (Dauer)
			const pricePerPerson = this.calculatePricePerPerson();
			const pricing = {
				price_per_person: pricePerPerson,
				total_price: pricePerPerson * this.playerCount
			};
			
			console.log('Preis berechnet:', pricing, 'Dauer:', this.selectedDuration, 'Spieler:', this.playerCount);

			// Slots direkt rendern (ohne AJAX)
			this.availableSlots.forEach(slot => {
				const slotEl = this.createTimeSlotElement(slot, pricing);
				grid.appendChild(slotEl);
			});

			// Nach Uhrzeit sortieren
			const slotElements = Array.from(grid.querySelectorAll('.ltb-time-slot'));
			slotElements.sort((a, b) => {
				const slotA = JSON.parse(a.dataset.slot);
				const slotB = JSON.parse(b.dataset.slot);
				return (parseInt(slotA.hour) || 0) - (parseInt(slotB.hour) || 0);
			});
			// Grid leeren und sortiert wieder einfügen
			grid.innerHTML = '';
			slotElements.forEach(el => grid.appendChild(el));
			console.log('Slots gerendert und sortiert:', slotElements.length);
		},
		
		// Preis pro Person basierend auf gewähltem Paket
		calculatePricePerPerson: function() {
			switch (this.selectedDuration) {
				case 1: return 25.00;  // 60 Min
				case 2: return 35.00;  // 120 Min
				case 3: return 45.00;  // 180 Min
				default: return 25.00;
			}
		},

		fetchSlotPricing: function(slot) {
			return new Promise((resolve) => {
				if (!this.selectedGameMode) {
					console.warn('Kein Spielmodus ausgewählt für Preisberechnung');
					resolve({ price_per_person: 0, total_price: 0, base_price: 0, extra_price: 0 });
					return;
				}

				const formData = new FormData();
				formData.append('action', 'ltb_get_slot_pricing');
				formData.append('nonce', ltbData.nonce);
				formData.append('date', slot.date);
				formData.append('game_mode', this.selectedGameMode);
				formData.append('person_count', this.playerCount);
				formData.append('duration', this.selectedDuration || 1);

				fetch(ltbData.ajaxUrl, {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success && data.data) {
						// Sicherstellen, dass Preise Zahlen sind
						const pricing = {
							price_per_person: parseFloat(data.data.price_per_person) || 0,
							total_price: parseFloat(data.data.total_price) || 0,
							base_price: parseFloat(data.data.base_price) || 0,
							extra_price: parseFloat(data.data.extra_price) || 0,
							duration: parseInt(data.data.duration) || 1,
						};
						console.log('Preisberechnung:', {
							personen: this.playerCount,
							dauer: this.selectedDuration,
							preis_pro_person: pricing.price_per_person,
							gesamtpreis: pricing.total_price
						});
						resolve(pricing);
					} else {
						console.warn('Fehler beim Abrufen des Preises:', data);
						resolve({ price_per_person: 0, total_price: 0, base_price: 0, extra_price: 0 });
					}
				})
				.catch((error) => {
					console.error('Fehler beim Preis-Abruf:', error);
					resolve({ price_per_person: 0, total_price: 0, base_price: 0, extra_price: 0 });
				});
			});
		},

		createTimeSlotElement: function(slot, pricing) {
			const slotEl = document.createElement('div');
			slotEl.className = 'ltb-time-slot';
			slotEl.dataset.slot = JSON.stringify(slot);

			// Startzeit
			const startTimeStr = slot.start.split(' ')[1].substring(0, 5);
			
			// Endzeit berechnen (Startzeit + Dauer)
			const startHour = parseInt(slot.hour) || parseInt(startTimeStr.split(':')[0]) || 0;
			const duration = this.selectedDuration || 1;
			const endHour = startHour + duration;
			const endTimeStr = String(endHour).padStart(2, '0') + ':00';
			
			// Zeitanzeige: "15:00 - 18:00"
			const timeDisplay = startTimeStr + ' - ' + endTimeStr;
			
			// Preis sicher als Zahl behandeln
			if (!pricing) {
				pricing = { price_per_person: 0, total_price: 0 };
			}
			
			// KEIN RABATT - nur normaler Preis anzeigen
			const pricePerPerson = typeof pricing.price_per_person === 'number' 
				? pricing.price_per_person 
				: parseFloat(pricing.price_per_person) || 0;

			const priceNum = Number(pricePerPerson) || 0;

			// Dauer-Info
			const durationText = duration === 1 ? '60 Min' : (duration === 2 ? '120 Min' : '180 Min');

			slotEl.innerHTML = `
				<div class="ltb-slot-time">${timeDisplay}</div>
				<div class="ltb-slot-duration">${durationText}</div>
				<div class="ltb-slot-pricing">
					${priceNum > 0 ? `<span class="ltb-slot-price">€${priceNum.toFixed(2)}</span>` : '<span class="ltb-slot-price">€0.00</span>'}
				</div>
				<div class="ltb-slot-availability">0/24 ${ltbData.strings.players || 'Spieler'}</div>
			`;

			slotEl.addEventListener('click', (e) => {
				e.preventDefault();
				e.stopPropagation();
				console.log('Slot geklickt, aktueller Schritt:', this.currentStep);
				this.selectTimeSlot(slot, pricing);
			});

			return slotEl;
		},

		selectTimeSlot: function(slot, pricing) {
			console.log('Zeitslot ausgewählt:', slot, 'Aktueller Schritt:', this.currentStep);
			
			// Sicherstellen, dass wir in Schritt 4 sind (Zeitslot-Auswahl)
			if (this.currentStep !== 4) {
				console.warn('Nicht in Schritt 4, aktueller Schritt:', this.currentStep);
				return;
			}
			
			const item = {
				booking_date: slot.date,
				start_time: slot.start.split(' ')[1].substring(0, 5) + ':00',
				booking_duration: this.selectedDuration || 1,
				game_mode: this.selectedGameMode,
				person_count: this.playerCount,
			};

			console.log('Füge Item zum Warenkorb hinzu:', item);
			this.addToCart(item);
		},

		addToCart: function(item) {
			console.log('addToCart aufgerufen, aktueller Schritt:', this.currentStep);
			console.log('Item:', item);
			
			const formData = new FormData();
			formData.append('action', 'ltb_add_to_cart');
			formData.append('nonce', ltbData.nonce);
			formData.append('booking_date', item.booking_date);
			formData.append('start_time', item.start_time);
			formData.append('booking_duration', item.booking_duration);
			formData.append('game_mode', item.game_mode);
			formData.append('person_count', item.person_count);

			fetch(ltbData.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
			.then(response => response.json())
			.then(data => {
				console.log('addToCart Response:', data);
				console.log('Cart aus Response:', data.data?.cart);
				console.log('Total aus Response:', data.data?.total);
				
				if (data.success) {
					// Cart-Daten direkt aus Response verwenden
					if (data.data && data.data.cart) {
						// Sicherstellen, dass cart ein Array ist
						this.cart = Array.isArray(data.data.cart) ? data.data.cart : Object.values(data.data.cart);
						console.log('Cart gesetzt:', this.cart);
						console.log('Cart Länge:', this.cart.length);
						this.renderCart(data.data.total);
					} else {
						// Fallback: Cart neu laden
						console.warn('Keine Cart-Daten in Response, lade Cart neu...');
						this.updateCart();
					}
					
					this.showMessage('success', 'Zum Warenkorb hinzugefügt!');
					
					// "Anderes Datum wählen" Button anzeigen
					const selectAnotherDateBtn = this.container.querySelector('.ltb-select-another-date');
					if (selectAnotherDateBtn) {
						selectAnotherDateBtn.style.display = 'inline-block';
					}
					
					// Sicherstellen, dass wir in Schritt 4 bleiben
					console.log('Nach addToCart - aktueller Schritt:', this.currentStep);
					if (this.currentStep !== 4) {
						console.warn('Schritt wurde geändert! Wechsel zurück zu Schritt 4');
						this.goToStep(4);
					}
				} else {
					this.showMessage('error', data.data?.message || 'Fehler beim Hinzufügen.');
				}
			})
			.catch((error) => {
				console.error('Fehler in addToCart:', error);
				this.showMessage('error', 'Fehler beim Hinzufügen zum Warenkorb.');
			});
		},

		updateCart: function() {
			const formData = new FormData();
			formData.append('action', 'ltb_get_cart');
			formData.append('nonce', ltbData.nonce);

			fetch(ltbData.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
			.then(response => response.json())
			.then(data => {
				console.log('updateCart Response:', data);
				if (data.success) {
					// Sicherstellen, dass cart ein Array ist
					const cartData = data.data.cart || [];
					this.cart = Array.isArray(cartData) ? cartData : Object.values(cartData);
					console.log('Cart nach updateCart:', this.cart);
					console.log('Cart Länge:', this.cart.length);
					this.renderCart(data.data.total);
				} else {
					console.warn('updateCart fehlgeschlagen:', data);
				}
			})
			.catch((error) => {
				console.error('Fehler in updateCart:', error);
			});
		},

		renderCart: function(total) {
			const cartItems = this.container.querySelector('.ltb-cart-items');
			const checkoutBtn = this.container.querySelector('.ltb-btn-checkout');

			if (!cartItems) {
				console.warn('Cart-Items Container nicht gefunden!');
				return;
			}

			// Sicherstellen, dass cart ein Array ist
			const cartArray = Array.isArray(this.cart) ? this.cart : Object.values(this.cart || {});
			const cartLength = cartArray.length;
			
			console.log('renderCart aufgerufen, Cart-Länge:', cartLength, 'Cart:', cartArray);

			if (cartLength === 0) {
				cartItems.innerHTML = '<p class="ltb-cart-empty">Warenkorb ist leer.</p>';
				if (checkoutBtn) checkoutBtn.disabled = true;
				
				// Gesamtsumme auf 0 setzen
				const subtotalEl = this.container.querySelector('.ltb-cart-subtotal');
				const totalEl = this.container.querySelector('.ltb-total-amount');
				const perPersonEl = this.container.querySelector('.ltb-per-person-amount');
				
				if (subtotalEl) subtotalEl.textContent = '€0.00';
				if (totalEl) totalEl.textContent = '€0.00';
				if (perPersonEl) perPersonEl.textContent = '€0.00';
				
				// Rabatte verstecken
				const discountEl = this.container.querySelector('.ltb-volume-discount');
				if (discountEl) discountEl.style.display = 'none';
				
				return;
			}

			if (checkoutBtn) checkoutBtn.disabled = false;

			let html = '';
			cartArray.forEach((item, index) => {
				const dateObj = new Date(item.booking_date);
				const dateStr = dateObj.toLocaleDateString('de-DE');
				const timeStr = item.start_time.substring(0, 5);

				html += `
					<div class="ltb-cart-item">
						<div class="ltb-cart-item-info">
							<div class="ltb-cart-item-mode">${item.game_mode}</div>
							<div class="ltb-cart-item-details">${dateStr} ${timeStr}</div>
							<div class="ltb-cart-item-players">${item.person_count} ${ltbData.strings.players || 'Spieler'}</div>
						</div>
						<div class="ltb-cart-item-price">€${parseFloat(item.total_price).toFixed(2)}</div>
						<button type="button" class="ltb-cart-remove" data-item-id="${item.item_id}">×</button>
					</div>
				`;
			});

			cartItems.innerHTML = html;

			// Remove-Buttons
			const removeBtns = cartItems.querySelectorAll('.ltb-cart-remove');
			removeBtns.forEach(btn => {
				btn.addEventListener('click', () => {
					const itemId = btn.dataset.itemId;
					this.removeFromCart(itemId);
				});
			});

			// Gesamtsumme aktualisieren - IMMER, auch wenn total undefined ist
			const subtotalEl = this.container.querySelector('.ltb-cart-subtotal');
			const totalEl = this.container.querySelector('.ltb-total-amount');
			const perPersonEl = this.container.querySelector('.ltb-per-person-amount');

			if (total && typeof total === 'object') {
				if (subtotalEl) subtotalEl.textContent = '€' + (total.subtotal || 0).toFixed(2);
				if (totalEl) totalEl.textContent = '€' + (total.total || 0).toFixed(2);
				if (perPersonEl && this.playerCount > 0) {
					perPersonEl.textContent = '€' + ((total.total || 0) / this.playerCount).toFixed(2);
				}
			} else {
				// Fallback: Preise auf 0 setzen
				if (subtotalEl) subtotalEl.textContent = '€0.00';
				if (totalEl) totalEl.textContent = '€0.00';
				if (perPersonEl) perPersonEl.textContent = '€0.00';
			}

			// Volumenrabatt IMMER verstecken (DEAKTIVIERT)
			const discountEl = this.container.querySelector('.ltb-volume-discount');
			const discountAmountEl = this.container.querySelector('.ltb-discount-amount');
			if (discountEl) discountEl.style.display = 'none';
			if (discountAmountEl) discountAmountEl.textContent = '-€0.00';
			
		},

		removeFromCart: function(itemId) {
			console.log('removeFromCart aufgerufen für Item:', itemId);
			
			const formData = new FormData();
			formData.append('action', 'ltb_remove_from_cart');
			formData.append('nonce', ltbData.nonce);
			formData.append('item_id', itemId);

			fetch(ltbData.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
			.then(response => response.json())
			.then(data => {
				console.log('removeFromCart Response:', data);
				if (data.success) {
					// Cart-Daten direkt aus Response verwenden
					if (data.data && data.data.cart) {
						// Sicherstellen, dass cart ein Array ist
						this.cart = Array.isArray(data.data.cart) ? data.data.cart : Object.values(data.data.cart);
						console.log('Cart nach Entfernen:', this.cart);
						console.log('Total nach Entfernen:', data.data.total);
						
						// IMMER renderCart mit total aufrufen, auch wenn leer
						const total = data.data.total || {
							subtotal: 0,
							volume_discount: 0,
							volume_discount_percent: 0,
							total: 0,
							game_count: 0
						};
						this.renderCart(total);
					} else {
						// Fallback: Cart neu laden
						this.updateCart();
					}
					this.showMessage('success', 'Aus Warenkorb entfernt.');
				} else {
					this.showMessage('error', data.data?.message || 'Fehler beim Entfernen.');
				}
			})
			.catch((error) => {
				console.error('Fehler in removeFromCart:', error);
				this.showMessage('error', 'Fehler beim Entfernen aus dem Warenkorb.');
			});
		},

		updateDiscountBanner: function() {
			// DEAKTIVIERT - keine Rabatt-Banner mehr
			const banner = this.container.querySelector('.ltb-discount-banner');
			if (banner) {
				banner.style.display = 'none';
			}
		},

		showCheckout: function() {
			console.log('showCheckout aufgerufen');
			const modal = this.container.querySelector('.ltb-checkout-modal');
			console.log('Modal gefunden:', modal);
			if (modal) {
				modal.style.display = 'flex';
				console.log('Modal angezeigt');
			} else {
				console.error('Modal NICHT gefunden!');
			}
		},

		hideCheckout: function() {
			const modal = this.container.querySelector('.ltb-checkout-modal');
			if (modal) {
				modal.style.display = 'none';
				// Form zurücksetzen
				const form = modal.querySelector('#ltb-checkout-form');
				if (form) {
					form.reset();
				}
			}
		},

		handleCheckout: function(e) {
			e.preventDefault();
			e.stopPropagation();

			console.log('handleCheckout aufgerufen');

			const form = e.target;
			const formData = new FormData(form);
			formData.append('action', 'ltb_create_booking');
			formData.append('nonce', ltbData.nonce);

			// Validierung
			const name = formData.get('name');
			const email = formData.get('email');
			const message = formData.get('message');
			const inquiryThreshold = parseInt(ltbData.inquiryThreshold) || 0; // 0 = keine Anfrage-Pflicht
			
			if (!name || !email) {
				this.showMessage('error', 'Bitte füllen Sie alle Pflichtfelder aus.');
				return;
			}
			
			// Prüfen, ob Nachricht bei Anfrage-Schwelle erforderlich ist
			if (inquiryThreshold > 0 && this.playerCount >= inquiryThreshold && !message) {
				this.showMessage('error', ltbData.strings.inquiryRequired || 'Bei dieser Spieleranzahl benötigen wir weitere Details. Bitte füllen Sie das Nachrichtenfeld aus.');
				return;
			}

			const submitBtn = form.querySelector('button[type="submit"]');
			const originalText = submitBtn.textContent;
			submitBtn.disabled = true;
			submitBtn.textContent = 'Wird verarbeitet...';

			console.log('Sende Buchungs-Request...', {
				name: name,
				email: email,
				cart: this.cart
			});

			fetch(ltbData.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
			.then(response => {
				console.log('Response Status:', response.status);
				return response.json();
			})
			.then(data => {
				console.log('Buchungs-Response:', data);
				if (data.success) {
					this.showMessage('success', data.data.message || 'Buchung erfolgreich!');
					form.reset();
					this.cart = []; // Cart leeren
					this.updateCart();
					this.goToStep(1);
					// Modal schließen mit kurzer Verzögerung, damit die Erfolgsmeldung sichtbar ist
					setTimeout(() => {
						this.hideCheckout();
					}, 500);
				} else {
					const errorMessage = data.data?.message || 'Fehler bei der Buchung.';
					this.showMessage('error', errorMessage);
					submitBtn.disabled = false;
					submitBtn.textContent = originalText;
					// Modal NICHT schließen bei Fehler, damit der Benutzer die Fehlermeldung sieht
				}
			})
			.catch((error) => {
				console.error('Fehler in handleCheckout:', error);
				this.showMessage('error', 'Fehler bei der Buchung. Bitte versuchen Sie es erneut.');
				submitBtn.disabled = false;
				submitBtn.textContent = originalText;
			});
		},

		formatDate: function(date) {
			const year = date.getFullYear();
			const month = String(date.getMonth() + 1).padStart(2, '0');
			const day = String(date.getDate()).padStart(2, '0');
			return year + '-' + month + '-' + day;
		},

		showMessage: function(type, message) {
			const messageEl = this.container.querySelector('.ltb-message');
			if (!messageEl) return;

			messageEl.className = 'ltb-message ltb-message-' + type;
			messageEl.textContent = message;
			messageEl.style.display = 'block';

			setTimeout(() => {
				messageEl.style.display = 'none';
			}, 5000);
		}
	};

	document.addEventListener('DOMContentLoaded', function() {
		LTB.init();
	});
})();
