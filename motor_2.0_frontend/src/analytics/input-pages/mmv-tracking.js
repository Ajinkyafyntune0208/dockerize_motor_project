import { typeRename } from "analytics/typeCheck";

//init
const we_track = window?.webengage;

export const _useMMVTracking = (mmv, mmvValue, type) => {
  if (we_track) {
    const fireEvent = (name, attrs) => {
      we_track.track(name, {
        ...attrs,
      });
    };
    let typeIns = {
      "Motor Insurance Type": typeRename(type),
    };
    //MMV
    switch (mmv) {
      case "brand":
        fireEvent("Select Brand for Vehicle", {
          ...typeIns,
          "Brand Name": mmvValue,
        });
        break;
      case "model":
        fireEvent("Select Model for Vehicle", {
          ...typeIns,
          "Model Name": mmvValue,
        });
        break;
      case "fuel-type":
        fireEvent("Select fuel type for Vehicle", {
          ...typeIns,
          "Fuel Type": mmvValue,
        });
        break;
      case "variant":
        fireEvent("Select Variant for Vehicle", {
          ...typeIns,
          "Variant Name": mmvValue,
        });
        break;
      case "rto":
        fireEvent("Select RTO for Vehicle", {
          ...typeIns,
          "RTO Name": mmvValue,
        });
        break;
      case "reg-year":
        fireEvent("Select Registration Year for Vehicle", {
          ...typeIns,
          Year: parseInt(mmvValue?.split("-")[2]),
        });
        break;
      default:
        break;
    }
  }
};
