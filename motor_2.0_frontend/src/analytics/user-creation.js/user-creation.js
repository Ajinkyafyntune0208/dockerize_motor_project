import { typeRename } from "analytics/typeCheck";
import { TypeReturn } from "modules/type";

//init
const webEngage = window?.webengage && window.webengage.user;
const we_track = window?.webengage;

//Lead-Page
export const _trackProfile = (profileAttributes) => {
  const { mobileNo, emailId, fullName, id } = profileAttributes;
  //processing name
  let firstName = "";
  let lastName = "";
  if (fullName) {
    let splitvalue = fullName && fullName.split(" ");
    if (splitvalue.length > 1) {
      lastName = splitvalue[splitvalue.length - 1];
      firstName = splitvalue.slice(0, -1).join(" ");
    }
  }
  //Webengage integration
  if (webEngage) {
    webEngage.setAttribute("we_phone", mobileNo);
    webEngage.setAttribute("we_email", emailId);
    webEngage.setAttribute("we_first_name", firstName);
    webEngage.setAttribute("we_last_name", lastName);
    webEngage.setAttribute("we_whatsapp_opt_in", true);
    webEngage.login(id);
  }
};

export const _trackVerification = (type) => {
  if (we_track && type) {
    we_track.track("Motor Insurance Initiated", {
      "Motor Insurance Type": typeRename(TypeReturn(type)),
    });

    import.meta.env.VITE_BROKER === "BAJAJ" &&
      import.meta.env.VITE_BASENAME === "general-insurance" &&
      we_track.track("Motor Insurance OTP Verified", {
        "Motor Insurance Type": typeRename(TypeReturn(type)),
      });
  }
};
