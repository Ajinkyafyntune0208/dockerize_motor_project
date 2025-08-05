import { typeRename } from "analytics/typeCheck";

//init
const we_track = window?.webengage;

export const _leadTrack = (data) => {
  if (data && we_track) {
    we_track.track("Motor Insurance Initiated", {
      "Motor Insurance Type": typeRename(data?.type),
    });
  }
};
