export const GradientPicker = (step) => {
  if (import.meta.env.VITE_BROKER === "TATA") {
    switch (step) {
      case "0":
        return [
          "linear-gradient(90deg, rgba(0,153,242,1) 0%, rgba(0,153,242,1) 100%);",
          `linear-gradient(90deg, rgba(0,153,242,1) 0%, rgba(31,134,244,1) 100%);`,
        ];
      case "1":
        return [
          "linear-gradient(90deg, rgba(31,134,244,1) 0%, rgba(31,134,244,1) 100%);",
          `linear-gradient(90deg, rgba(31,134,244,1) 0%, rgba(101,90,250,1) 100%);`,
        ];
      case "2":
        return [
          "linear-gradient(90deg, rgba(101,90,250,1) 0%, rgba(101,90,250,1) 100%)",
          `linear-gradient(90deg, rgba(101,90,250,1) 0%, rgba(113,82,251,1) 100%)`,
        ];
      case "3":
        return [
          "linear-gradient(90deg, rgba(113,82,251,1) 0%, rgba(113,82,251,1) 100%)",
          `linear-gradient(90deg, rgba(113,82,251,1) 0%, rgba(128,73,252,1) 100%)`,
        ];
      case "4":
        return [
          "linear-gradient(90deg, rgba(128,73,252,1) 0%, rgba(128,73,252,1) 100%)",
          `linear-gradient(90deg, rgba(128,73,252,1) 0%, rgba(145,63,253,1) 100%)`,
        ];
      case "5":
        return [
          "linear-gradient(90deg, rgba(145,63,253,1) 0%, rgba(145,63,253,1) 100%)",
          `linear-gradient(90deg, rgba(145,63,253,1) 0%, rgba(154,57,254,1) 100%)`,
        ];
      default:
        break;
    }
  }
};

export const TitleFn = (Step, lessthan600) => {
  switch (Step) {
    case 1:
      return lessthan600
        ? "Select Vehicle's Brand"
        : "Select the Brand of your Vehicle";
    case 2:
      return lessthan600
        ? "Select Vehicle's Model"
        : "Select the Model of your Vehicle";
    case 3:
      return lessthan600
        ? "Select Fuel Type"
        : "Select Fuel type of your Vehicle";
    case 4:
      return lessthan600
        ? "Select Vehicle's Variant"
        : "Select the Variant of your Vehicle";
    case 5:
      return lessthan600 ? "Enter RTO Details" : "Enter RTO Details";
    case 6:
      return lessthan600
        ? "Select Registration Year"
        : "Select the Vehicle Invoice Year";
    default:
      break;
  }
};

//fuel type
export const FuelType = (availableTypes) => [
  availableTypes?.includes("PETROL") && {
    name: "Petrol",
    label: "Petrol",
    value: "PETROL",
    id: "PETROL",
    logo:
      import.meta.env.VITE_BROKER === "UIB"
        ? `${
            import.meta.env.VITE_BASENAME !== "NA"
              ? `/${import.meta.env.VITE_BASENAME}`
              : ""
          }/assets/images/new-petrol.svg`
        : `${
            import.meta.env.VITE_BASENAME !== "NA"
              ? `/${import.meta.env.VITE_BASENAME}`
              : ""
          }/assets/images/petrol10.png`,
  },
  availableTypes?.includes("DIESEL") && {
    name: "Diesel",
    label: "Diesel",
    value: "DIESEL",
    id: "DIESEL",
    logo:
      import.meta.env.VITE_BROKER === "UIB"
        ? `${
            import.meta.env.VITE_BASENAME !== "NA"
              ? `/${import.meta.env.VITE_BASENAME}`
              : ""
          }/assets/images/oil2.svg`
        : `${
            import.meta.env.VITE_BASENAME !== "NA"
              ? `/${import.meta.env.VITE_BASENAME}`
              : ""
          }/assets/images/oil2.png`,
  },
  (availableTypes?.includes("CNG") || availableTypes?.includes("LPG")) && {
    name: "Inbuilt CNG/LPG",
    label: "Inbuilt CNG/LPG",
    value: "CNG",
    id: "CNG",
    logo:
      import.meta.env.VITE_BROKER === "UIB"
        ? `${
            import.meta.env.VITE_BASENAME !== "NA"
              ? `/${import.meta.env.VITE_BASENAME}`
              : ""
          }/assets/images/cng3.svg`
        : `${
            import.meta.env.VITE_BASENAME !== "NA"
              ? `/${import.meta.env.VITE_BASENAME}`
              : ""
          }/assets/images/cng3.png`,
  },
  availableTypes?.includes("ELECTRIC") && {
    name: "Electric",
    label: "Electric",
    value: "ELECTRIC",
    id: "ELECTRIC",
    logo:
      import.meta.env.VITE_BROKER === "UIB"
        ? `${
            import.meta.env.VITE_BASENAME !== "NA"
              ? `/${import.meta.env.VITE_BASENAME}`
              : ""
          }/assets/images/electric.svg`
        : `${
            import.meta.env.VITE_BASENAME !== "NA"
              ? `/${import.meta.env.VITE_BASENAME}`
              : ""
          }/assets/images/electric.png`,
  },
];

export const Switcher = (Step, setStep, temp_data, type) => {
  switch (Step) {
    case 1:
      setStep((prev) => prev - 1);
      break;
    case 2:
      setStep((prev) => prev - 1);
      break;
    case 3:
      setStep((prev) => prev - 1);
      break;
    case 4:
      if (type === "bike") {
        //skipping fuel type in bike
        setStep(2);
      } else {
        setStep((prev) => prev - 1);
      }
      break;
    case 5:
      setStep((prev) => prev - 1);
      break;
    case 6:
      //If regNo is availvale then rto step will be skipped
      if (
        temp_data?.journeyType === 1 &&
        temp_data?.regNo &&
        !(temp_data?.regNo[0] * 1)
      ) {
        setStep(4);
      }
      //  rto step mandatory for NB & if regNo is not availvale
      else {
        setStep((prev) => prev - 1);
      }
      break;
    default:
      break;
  }
};

export const NoOfSteppers = (temp_data, type) => {
  //In case of bike, fuel type selection is not required as it will be mentioned in version name
  if (type === "bike") {
    //Incase of journey with regNo and NB the no. of steps = 4
    if (
      (temp_data?.journeyType === 1 &&
        temp_data?.regNo &&
        !(temp_data?.regNo[0] * 1)) ||
      Number(temp_data?.journeyType) === 3 ||
      temp_data?.regNo === "NEW"
    ) {
      return "customStep2";
    }
    //Incase of journey without regNo and rollover the no. of steps = 5
    else {
      return "customStep";
    }
  }
  //In journeys other than bike, fuel type selection is mandatory
  else {
    //Incase of journey with regNo and NB the no. of steps = 5
    if (
      (temp_data?.journeyType === 1 &&
        temp_data?.regNo &&
        !(temp_data?.regNo[0] * 1)) ||
      Number(temp_data?.journeyType) === 3 ||
      temp_data?.regNo === "NEW"
    ) {
      return "customStep";
    }
    //Incase of journey without regNo and rollover the no. of steps = 6
    else {
      return "general";
    }
  }
};
