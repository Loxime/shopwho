document
  .querySelectorAll('[data-address-autocomplete]')
  .forEach((form) => {
    const endpoint = form.dataset.addressLookupUrl;
    const line1 = form.querySelector('[data-address-line1]');
    const postalCode = form.querySelector(
      '[data-address-postal-code]'
    );
    const city = form.querySelector('[data-address-city]');
    const country = form.querySelector(
      '[data-address-country]'
    );
    const feedback = form.querySelector(
      '[data-address-feedback]'
    );
    const suggestions = form.querySelector(
      '[data-address-suggestions]'
    );

    if (
      !endpoint
      || !line1
      || !postalCode
      || !city
      || !country
      || !feedback
      || !suggestions
    ) {
      return;
    }

    let timer = null;
    let controller = null;

    const clearSuggestions = () => {
      suggestions.replaceChildren();
      suggestions.hidden = true;
    };

    const setFeedback = (
      message = '',
      state = ''
    ) => {
      feedback.textContent = message;

      if (state) {
        feedback.dataset.state = state;
      } else {
        delete feedback.dataset.state;
      }
    };

    const buildQuery = () => [
      line1.value,
      postalCode.value,
      city.value,
    ]
      .map((value) => value.trim())
      .filter(Boolean)
      .join(' ');

    const selectSuggestion = (
      suggestion
    ) => {
      line1.value = suggestion.line1;
      postalCode.value = suggestion.postalCode;
      city.value = suggestion.city;

      clearSuggestions();

      setFeedback(
        `Adresse reconnue : ${suggestion.label}`,
        'success'
      );
    };

    const renderSuggestions = (
      items
    ) => {
      clearSuggestions();

      if (items.length === 0) {
        setFeedback(
          'Aucune adresse correspondante trouvée.',
          'empty'
        );

        return;
      }

      items.forEach((suggestion) => {
        const button = document.createElement(
          'button'
        );

        button.type = 'button';
        button.className = 'address-suggestion';
        button.setAttribute('role', 'option');
        button.textContent = suggestion.label;

        button.addEventListener(
          'click',
          () => selectSuggestion(suggestion)
        );

        suggestions.append(button);
      });

      suggestions.hidden = false;

      setFeedback(
        'Sélectionnez une adresse proposée.',
        'info'
      );
    };

    const search = async () => {
      const query = buildQuery();

      if (
        country.value !== 'FR'
        || query.length < 3
      ) {
        clearSuggestions();
        setFeedback();

        return;
      }

      if (controller) {
        controller.abort();
      }

      controller = new AbortController();

      setFeedback(
        'Recherche de l’adresse…',
        'loading'
      );

      try {
        const response = await fetch(
          `${endpoint}?q=${encodeURIComponent(query)}`,
          {
            headers: {
              Accept: 'application/json',
            },
            signal: controller.signal,
          }
        );

        const payload = await response.json();

        if (
          !response.ok
          || payload.available === false
        ) {
          throw new Error(
            'Address lookup unavailable'
          );
        }

        renderSuggestions(
          Array.isArray(payload.suggestions)
            ? payload.suggestions
            : []
        );
      } catch (error) {
        if (error.name === 'AbortError') {
          return;
        }

        clearSuggestions();

        setFeedback(
          'La recherche d’adresse est momentanément indisponible.',
          'error'
        );
      }
    };

    const scheduleSearch = () => {
      clearTimeout(timer);

      setFeedback();

      timer = setTimeout(
        search,
        350
      );
    };

    [
      line1,
      postalCode,
      city,
    ].forEach((field) => {
      field.addEventListener(
        'input',
        scheduleSearch
      );
    });

    country.addEventListener(
      'change',
      scheduleSearch
    );

    document.addEventListener(
      'click',
      (event) => {
        if (!form.contains(event.target)) {
          clearSuggestions();
        }
      }
    );
  });
