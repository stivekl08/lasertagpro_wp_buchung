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

			// Datum-Navigation
			const prevDateBtn = this.container.querySelector('.ltb-prev-date');
			const nextDateBtn = this.container.querySelector('.ltb-next-date');
			if (prevDateBtn) prevDateBtn.addEventListener('click', () => this.navigateDate(-1));
			if (nextDateBtn) nextDateBtn.addEventListener('click', () => this.navigateDate(1));

			// Anderes Datum wählen Button
			const selectAnotherDateBtn = this.container.querySelector('.ltb-select-another-date');
			if (selectAnotherDateBtn) {
				selectAnotherDateBtn.addEventListener('click', () => this.goToStep(4));
			}

			// Warenkorb
			const checkoutBtn = this.container.querySelector('.ltb-btn-checkout');
			if (checkoutBtn) checkoutBtn.addEventListener('click', () => this.showCheckout());

			const promoBtn = this.container.querySelector('.ltb-btn-promo');
			if (promoBtn) promoBtn.addEventListener('click', () => this.applyPromoCode());

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
			if (step === 3 && !this.selectedGameMode) {
				this.showMessage('error', 'Bitte wählen Sie einen Spielmodus.');
				return;
			}
			if (step === 4 && !this.selectedDuration) {
				this.showMessage('error', 'Bitte wählen Sie eine Buchungsdauer.');
				return;
			}
			if (step === 5 && !this.currentDate) {
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

				// Spezielle Aktionen pro Schritt
				if (step === 3) {
					// Event-Listener für Dauer-Buttons sicherstellen
					const durationBtns = this.container.querySelectorAll('.ltb-duration-btn');
					durationBtns.forEach(btn => {
						// Alte Listener entfernen (falls vorhanden)
						const newBtn = btn.cloneNode(true);
						btn.parentNode.replaceChild(newBtn, btn);
						// Neuen Listener hinzufügen
						newBtn.addEventListener('click', (e) => {
							e.preventDefault();
							e.stopPropagation();
							console.log('Dauer-Button geklickt:', newBtn.dataset.duration);
							this.selectDuration(newBtn);
						});
					});
				} else if (step === 4) {
					this.updateDateDisplay();
				} else if (step === 5) {
					console.log('Wechsel zu Schritt 5 - lade Zeitslots...');
					// Kurze Verzögerung, damit das DOM aktualisiert wird
					setTimeout(() => {
						this.loadTimeSlots();
					}, 100);
				}
			} else {
				console.error('Schritt', step, 'nicht gefunden!');
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
			const nextBtn = this.container.querySelector('.ltb-step-3 .ltb-next-step');
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
			if (this.currentStep === 5) {
				this.loadTimeSlots();
			}
		},

		updateDateDisplay: function() {
			const days = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
			const months = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
			
			const dayName = days[this.currentDate.getDay()];
			const day = this.currentDate.getDate();
			const month = months[this.currentDate.getMonth()];

			const dateDisplay = this.container.querySelector('.ltb-date-display');
			if (dateDisplay) {
				dateDisplay.textContent = dayName + ', ' + day + ', ' + month;
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

			// Prüfen ob Schritt 5 wirklich sichtbar ist
			const step5 = this.container.querySelector('.ltb-step-5');
			if (!step5 || step5.style.display === 'none') {
				console.warn('Schritt 5 ist nicht sichtbar!');
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

			// Slots rendern
			const promises = this.availableSlots.map(slot => {
				return this.fetchSlotPricing(slot).then(pricing => {
					if (!pricing) {
						console.warn('Keine Preis-Daten für Slot:', slot);
						pricing = { price_per_person: 0, total_price: 0 };
					}
					const slotEl = this.createTimeSlotElement(slot, pricing);
					grid.appendChild(slotEl);
					console.log('Slot hinzugefügt:', slotEl, 'Grid hat jetzt', grid.children.length, 'Kinder');
				}).catch(error => {
					console.error('Fehler beim Rendern des Slots:', error, slot);
				});
			});

			Promise.all(promises).then(() => {
				console.log('Alle Slots gerendert. Grid hat', grid.children.length, 'Elemente');
				// Prüfen ob Grid sichtbar ist
				const computedStyle = window.getComputedStyle(grid);
				console.log('Grid CSS:', {
					display: computedStyle.display,
					visibility: computedStyle.visibility,
					opacity: computedStyle.opacity,
					height: computedStyle.height,
					width: computedStyle.width
				});
			});
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

			const timeStr = slot.start.split(' ')[1].substring(0, 5);
			
			// Preis sicher als Zahl behandeln
			if (!pricing) {
				pricing = { price_per_person: 0, total_price: 0 };
			}
			
			// KEIN RABATT - nur normaler Preis anzeigen
			const pricePerPerson = typeof pricing.price_per_person === 'number' 
				? pricing.price_per_person 
				: parseFloat(pricing.price_per_person) || 0;

			const priceNum = Number(pricePerPerson) || 0;

			// KEIN RABATT - NUR EIN PREIS!
			slotEl.innerHTML = `
				<div class="ltb-slot-time">${timeStr}</div>
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
			
			// Sicherstellen, dass wir in Schritt 5 bleiben
			if (this.currentStep !== 5) {
				console.warn('Nicht in Schritt 5, aktueller Schritt:', this.currentStep);
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
					
					// Sicherstellen, dass wir in Schritt 5 bleiben
					console.log('Nach addToCart - aktueller Schritt:', this.currentStep);
					if (this.currentStep !== 5) {
						console.warn('Schritt wurde geändert! Wechsel zurück zu Schritt 5');
						this.goToStep(5);
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
				const promoDiscountEl = this.container.querySelector('.ltb-promo-discount');
				if (discountEl) discountEl.style.display = 'none';
				if (promoDiscountEl) promoDiscountEl.style.display = 'none';
				
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
			
			// Promo-Code-Rabatt
			const promoDiscountEl = this.container.querySelector('.ltb-promo-discount');
			const promoAmountEl = this.container.querySelector('.ltb-promo-amount');
			if (total && typeof total === 'object' && total.promo_discount > 0) {
				if (promoDiscountEl) promoDiscountEl.style.display = 'flex';
				if (promoAmountEl) promoAmountEl.textContent = '-€' + total.promo_discount.toFixed(2);
			} else {
				if (promoDiscountEl) promoDiscountEl.style.display = 'none';
				if (promoAmountEl) promoAmountEl.textContent = '-€0.00';
			}
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
							promo_discount: 0,
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

		applyPromoCode: function() {
			const codeInput = this.container.querySelector('#ltb-promo-code');
			if (!codeInput || !codeInput.value) return;

			const formData = new FormData();
			formData.append('action', 'ltb_validate_promo');
			formData.append('nonce', ltbData.nonce);
			formData.append('promo_code', codeInput.value);

			fetch(ltbData.ajaxUrl, {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					this.showMessage('success', 'Promo-Code erfolgreich angewendet!');
					this.updateCart();
				} else {
					this.showMessage('error', data.data.message || 'Ungültiger Promo-Code.');
				}
			});
		},

		showCheckout: function() {
			const modal = this.container.querySelector('.ltb-checkout-modal');
			if (modal) modal.style.display = 'flex';
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

			const promoInput = this.container.querySelector('#ltb-promo-code');
			if (promoInput && promoInput.value) {
				formData.append('promo_code', promoInput.value);
			}

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
