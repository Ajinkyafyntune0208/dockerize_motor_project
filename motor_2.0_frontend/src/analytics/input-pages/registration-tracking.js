import { typeRename } from "analytics/typeCheck";
import { PushEventToCt } from "../clevertap";
//init
const we_track = window?.webengage;

const EventName = (vehicle) => {
  switch (vehicle) {
    case "car":
      return "HI_Portal_4W_";
    case "bike":
      return "Hi_Portal_2W_";
    default:
      return "Hi_Portal_CV_";
      break;
  }
};

export const _pageVisit = (type, temp_data) => {
  if (window?.clevertap) {
    let dataObj = {
      URL: window.location.href,
      ["Referrer URL"]: document.referrer,
      ["Page Title"]: document.title,
      ["Name"]: `${temp_data?.firstName}${" "}${
        temp_data?.lastName ? `${temp_data?.lastName}` : ``
      }`,
      ["Ph Number"]: temp_data?.mobileNo,
      ["Email ID"]: temp_data?.emailId,
    };
    PushEventToCt(
      EventName(
        (
          temp_data?.subProduct?.parent?.productSubTypeCode || type
        ).toLowerCase()
      ) + "RegNo_ PageVisit",
      dataObj,
      temp_data
    );
  }
};

export const _rcTracking = (rc, type, temp_data) => {
  if (window?.clevertap) {
    let dataObj = {
      URL: window.location.href,
      ["Page Title"]: document.title,
      ["Referrer URL"]: document.referrer,
      ["Vehicle Registration Number entered"]: rc,
      ["Name"]: `${temp_data?.firstName}${" "}${
        temp_data?.lastName ? `${temp_data?.lastName}` : ``
      }`,
      ["Ph Number"]: temp_data?.mobileNo,
      ["Email ID"]: temp_data?.emailId,
    };
    PushEventToCt(
      EventName(
        (
          temp_data?.subProduct?.parent?.productSubTypeCode || type
        ).toLowerCase()
      ) + "RegNo_Entered",
      dataObj,
      temp_data
    );
  }
  if (rc && we_track) {
    we_track.track("View Quote for Motor Insurance", {
      "Registration Number": rc,
      "Motor Insurance Type": typeRename(type),
    });
  }
};

export const _norcTracking = (type, temp_data) => {
  if (window?.clevertap) {
    let dataObj = {
      URL: window.location.href,
      ["Referrer URL"]: document.referrer,
      ["Page Title"]: document.title,
      ["Name"]: `${temp_data?.firstName}${" "}${
        temp_data?.lastName ? `${temp_data?.lastName}` : ``
      }`,
      ["Ph Number"]: temp_data?.mobileNo,
      ["Email ID"]: temp_data?.emailId,
    };
    PushEventToCt(
      EventName(
        (
          temp_data?.subProduct?.parent?.productSubTypeCode || type
        ).toLowerCase()
      ) + "ManualJourney",
      dataObj,
      temp_data
    );
  }
  we_track &&
    we_track.track("Proceed without Vehicle Number", {
      "Motor Insurance Type": typeRename(type),
    });
};

export const _newBusiness = (type, temp_data) => {
  if (window?.clevertap) {
    let dataObj = {
      URL: window.location.href,
      ["Referrer URL"]: document.referrer,
      ["Page Title"]: document.title,
      ["Name"]: `${temp_data?.firstName}${" "}${
        temp_data?.lastName ? `${temp_data?.lastName}` : ``
      }`,
      ["Ph Number"]: temp_data?.mobileNo,
      ["Email ID"]: temp_data?.emailId,
    };
    PushEventToCt(
      EventName(
        (
          temp_data?.subProduct?.parent?.productSubTypeCode || type
        ).toLowerCase()
      ) + "NewVehicleJounrey",
      dataObj,
      temp_data
    );
  }
  we_track &&
    we_track.track("New Vehicle Quotation Initiated", {
      "Motor Insurance Type": typeRename(type),
    });
};
