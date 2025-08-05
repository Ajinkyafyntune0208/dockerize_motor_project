export const EvaluateChannels = (theme_conf, destruct, page) => {
  return (
    (theme_conf?.broker_config?.broker_asset?.communication_configuration
      ?.compare?.[destruct] &&
      window.location.href.includes("compare-quote")) ||
    (theme_conf?.broker_config?.broker_asset?.communication_configuration?.[
      page
    ]?.[destruct] &&
      (window.location.href.includes("quotes") ||
        window.location.href.includes("proposal-page"))) ||
    (theme_conf?.broker_config?.broker_asset?.communication_configuration
      ?.breakin_success?.[destruct] &&
      window.location.href.includes("successful")) ||
    (theme_conf?.broker_config?.broker_asset?.communication_configuration
      ?.payment_success?.[destruct] &&
      window.location.href.includes("payment-success")) ||
    (theme_conf?.broker_config?.broker_asset?.communication_configuration
      ?.payment_failure?.[destruct] &&
      window.location.href.includes("payment-failure"))
  );
};
