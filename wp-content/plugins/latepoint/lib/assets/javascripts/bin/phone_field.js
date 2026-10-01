/*
 * Version adapter for the bundled intl-tel-input library (v29.2.3, global lp_intlTelInput with
 * statics folded on). Every call site that needs to read/write a phone field goes through the
 * helpers below instead of talking to the library directly.
 */

function latepoint_iti_get_instance(el) {
  if (!el) return null;
  if (!window.lp_intlTelInput) return null;
  return window.lp_intlTelInput.getInstance(el);
}

function latepoint_iti_is_ready() {
  return !!(window.lp_intlTelInput && window.lp_intlTelInput.utils);
}

function latepoint_iti_get_e164(el) {
  const instance = latepoint_iti_get_instance(el);
  if (!instance) return '';
  return instance.getNumber('E164');
}

function latepoint_iti_is_valid(el) {
  const instance = latepoint_iti_get_instance(el);
  return !!instance && instance.isValidNumber();
}

function latepoint_iti_get_only_countries() {
  let onlyCountries = JSON.parse(latepoint_helper.included_phone_countries);
  // Remedy a quirk with json_encode(EMPTY_ARRAY)
  if (onlyCountries.length === 1 && onlyCountries[0] === "") {
    onlyCountries = [];
  }
  return onlyCountries;
}

function latepoint_iti_get_default_country_code(onlyCountries) {
  let defaultCountryCode = latepoint_helper.default_phone_country;
  if (onlyCountries.length && !onlyCountries.includes(defaultCountryCode)) {
    defaultCountryCode = onlyCountries[0];
  }
  return defaultCountryCode;
}

function latepoint_iti_init($elem) {
  const jsElem = $elem[0];

  // First priority is to prevent duplicates (common in non-document.body contexts)
  if (!jsElem || latepoint_iti_get_instance(jsElem)) return;

  const onlyCountries = latepoint_iti_get_only_countries();
  const defaultCountryCode = latepoint_iti_get_default_country_code(onlyCountries);

  window.lp_intlTelInput(jsElem, {
    dropdownParent: document.body,
    numberDisplayFormat: 'NATIONAL',
    placeholderNumberPolicy: 'AGGRESSIVE',
    async initialCountryLookup () {
      const cookieName = 'latepoint_phone_country';

      if (latepoint_has_cookie(cookieName)) {
        return latepoint_get_cookie(cookieName);
      }
      try {
        // dataType must be requested via the $.ajax object form - $.get(url, data, "jsonp")
        // does NOT set dataType (the string lands in the "success" callback slot instead),
        // so this would silently fall back to a same-origin XHR and hit ipinfo.io's CORS policy.
        const response = await jQuery.ajax({ url: 'https://ipinfo.io', dataType: 'jsonp' });
        if (response && response.country) {
          const countryCode = response.country.toLowerCase();
          latepoint_set_cookie(cookieName, countryCode);
          return countryCode;
        }
      } catch (e) {
        // ipinfo.io unreachable/blocked - fall through to the configured default
      }
      return defaultCountryCode;
    },
    countrySelectorMode: onlyCountries.length === 1 ? 'OFF' : 'AUTO',
    // Default is true, which sizes the dropdown to match the phone input's own width - too
    // narrow to fit a country name and dial code on one line, so they wrap and rows balloon
    // in height. Let the dropdown size to its own content instead.
    matchDropdownWidth: false,
    onlyCountries: onlyCountries.length ? onlyCountries : null,
    countryOrder: onlyCountries.length ? null : ['us', 'gb'],
    // wp_localize_script casts every scalar to a string ("1"/""), but v29 validates these two
    // options as real booleans and silently ignores them (and falls back to its own default)
    // if they're not - coerce explicitly rather than relying on JS truthiness alone.
    separateDialCode: !!latepoint_helper.is_enabled_show_dial_code_with_flag,
    strictMode: !!latepoint_helper.mask_phone_number_fields,
    strictRejectAnimation: true,
    searchInputClass: 'lp_iti__search-input-lp',
    countryNameLocale: latepoint_helper.phone_country_name_locale,
    uiTranslations: latepoint_helper.phone_i18n
  });

  // v29 anchors the detached dropdown (CSS `left: anchor(left)`) to the tel input itself, which
  // is correct for stock's absolute-overlay layout but not for our flex-sibling one (see the
  // country-container/tel-input rules in the CSS overrides) - the input no longer starts at the
  // field's left edge, so the dropdown opens shifted right under it instead of flush with the
  // field like the flag button is. The library assigns the input's anchor-name lazily, the first
  // time the dropdown opens, so move it from the input onto the country-container (the flag
  // button, which does start flush with the field) at that same moment via its public
  // "open:countryselector" event - one-time, same as the library's own assignment.
  jsElem.addEventListener('open:countryselector', function realignDropdownAnchor() {
    const anchorName = getComputedStyle(jsElem).anchorName;
    if (!anchorName || anchorName === 'none') return;
    const countryContainer = jsElem.closest('.lp_iti')?.querySelector('.lp_iti__country-container');
    if (!countryContainer) return;
    countryContainer.style.anchorName = anchorName;
    jsElem.style.anchorName = 'none';
    jsElem.removeEventListener('open:countryselector', realignDropdownAnchor);
  }, { once: true });
}
