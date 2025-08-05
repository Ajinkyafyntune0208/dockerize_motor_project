import swal from "sweetalert";

//This Config is used to block selected sections.
export const BlockedSections = (broker, typeCon) => {
  switch (broker) {
    case "ACE":
      return [typeCon === "PCV" && "unnamed pa cover"];
    // case "all":
    //   return [typeCon && "NCB Protection"];
    default:
      return [];
  }
};

export const errorAlert = () => {
  swal("Error", "Values cannot be empty", "error");
};
