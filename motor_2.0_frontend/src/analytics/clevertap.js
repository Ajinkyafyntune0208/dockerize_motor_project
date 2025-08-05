import { isB2B } from "utils";

export const CtUserLogin = (identity, profileUpdate, data, temp_data) => {
  if (!isB2B(temp_data)) {
    let clevertap = window?.clevertap;
    if (
      clevertap &&
      (identity.length === 10)
    ) {
      clevertap["onUserLogin"].push(
        profileUpdate
          ? {
              Site: {
                [data?.eventName]: data?.policyNo,
              },
            }
          : {
              Site: {
                Identity: "+91" + identity,
              },
            }
      );
    }
  }
};

export const PushEventToCt = (et, dataObj, temp_data) => {
 const baseName = import.meta.env.VITE_BASENAME === "NA" ?  "" : import.meta.env.VITE_BASENAME
  const redirectionUrl = `${window.location.origin}${baseName ?  `/${baseName}` : ""}/resume-journey${window.location.search}`
  if (!isB2B(temp_data)) {
    let clevertap = window?.clevertap;
    if (clevertap && et) {
      const defaultKeys = {
        Date: new Date(),
        URL: window.location.href,
        REDIRECTION_URL: redirectionUrl,
        Source_IP: temp_data?.Source_IP,
        Referrer: document.referrer,
        ["Page Name"]: window.location.pathname.split("/").pop(),
        ["Page Title"]: document.title,
      };
      clevertap.event.push(et, { ...defaultKeys, ...dataObj });
    }
  }
};
