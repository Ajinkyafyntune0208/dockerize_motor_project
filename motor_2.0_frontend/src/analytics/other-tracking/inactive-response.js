//init
const we_track = window?.webengage;

export const inactiveTracking = (response, type) => {
  if (we_track && type) {
    we_track.track("Inactive Response", {
      "Response Clicked": response,
      "Insurance Page": type,
    });
  }
};
