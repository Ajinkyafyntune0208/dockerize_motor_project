export const TypeReturn = (type) => {
  switch (type) {
    case "car-insurance":
      return "car";
    case "four-wheeler":
      return "car";
    case "fourwheeler":
      return "car";
    case "bike-insurance":
      return "bike";
    case "two-wheeler-insurance":
      return "bike";
    case "two-wheeler":
      return "bike";
    case "commercial-vehicle-insurance":
      return "cv";
    case "pcv":
      return "cv";
    default:
      return type;
  }
};

export const TypeCategory = (type) => {
  switch (type) {
    case "car":
      return "car";
    case "bike":
      return "two-wheeler";
    case "cv":
      return "commercial-vehicle";
    default:
      return type;
  }
};

export const typeRoute = () => {
  if (
    window.location.href.includes("/car/") ||
    window.location.href.includes("/car-insurance/")
  ) {
    return "car";
  } else if (
    window.location.href.includes("/bike/") ||
    window.location.href.includes("/bike-insurance/") ||
    window.location.href.includes("/two-wheeler-insurance/")
  ) {
    return "bike";
  } else {
    return "cv";
  }
};

export const subroutes = [
  "car",
  "bike",
  "cv",
  "car-insurance",
  "bike-insurance",
  "two-wheeler-insurance",
  "commercial-vehicle-insurance",
  "four-wheeler",
  "two-wheeler"
];
