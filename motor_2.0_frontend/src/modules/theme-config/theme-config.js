//-----------------ABIBL-----------------

const ABIBL = {
  proposalCardSummary: {
    background: "linear-gradient(90deg,#95181A 0%,#95181A 100%)",
  },
  proposalCardActive: {
    background: "linear-gradient(90deg,#c7222a 0%,#c7222a 100%)",
  },
  proposalProceedBtn: {
    hex1: "#C7222A",
    hex2: "#C7222A",
  },
  genderProposal: {
    background: "radial-gradient(circle,rgb(199 34 42) 17%,rgb(179 45 51) 85%)",
    boxShadow: "6.994px 5.664px 21px #fffff",
  },
  questionsProposal: {
    color: "#C7222A",
    toggleBackgroundColor: "#C7222A",
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "danger",
    linkColor: "#C7222A",
    iconColor: "#C7222A",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff;",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #C7222A;",
    liBorder: "2px solid #C7222A;",
  },
  CheckCircle: {
    backgroundImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: "#c7222a",
    border: " 1px solid #c7222a;",
  },
  CheckBox: {
    color: "#f",
    border: "1px solid #000",
  },
  Header: {
    color: "#c7222a",
    border: " 1px solid #c7222a",
    borderLogo: " 2.5px solid #c7222a",
    hex1: "#c7222a",
    hex2: "#c7222a",
  },
  FilterConatiner: {
    color: "#c7222a",
    lightColor: "#DA9089",
    lightBorder: ".5px solid #DA9089",
    lightBorder1: "1px solid #DA9089",
    clearAllIcon: "",
    editIconColor: "#000",
    clearAllTextColor: "#000",
  },
  QuoteCard: {
    color: "#c7222a",
    color2: " #8B151B !important",
    color3: "#c7222a",
    border: " 1px solid #c7222a",
    border2: "1px solid  #8B151B !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #DA9089, 0 10px 10px -5px #DA9089",
    mouseHoverShadow: "0px 0px 8px 0px #DA9089",
    ribbonBackground:
      "linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(218,144,137,1) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 100%)",
  },
  QuotePopups: {
    color: "#c7222a",
    border: "1px solid #c7222a",
    hex1: "#c7222a",
    hex2: "#c7222a",
    color2: " #8B151B",
    color3: " #8B151B !important",
    border2: "1px solid  #8B151B",
    border3: "2px solid #c7222a",
    lg: "-webkit-linear-gradient(-134deg,#c7222a,#d9b7bb)",
    prevpopBorder: "33px solid #c7222a",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    borderRadius: "5px",
    linkColor: "#c7222a",
    navColor: " #DA9089 !important",
    shadowCheck:
      "rgba(0, 0, 0, 0.16) 0px 10px 36px 0px, rgba(0, 0, 0, 0.06) 0px 0px 0px 1px",

    fontColor: "white",
    filterPropertyCheckBox:
      "invert(22%) sepia(76%) saturate(2340%) hue-rotate(338deg) brightness(89%) contrast(99%)",
    fontFamily: "pfhandbooksans_regular",
    fontFamilyBold: "pfhandbooksans_medium",
    moneyBackground: " #EDC9C6 !important",
    filterShadow: " 0 9px 13px #cfcfcf",
    checkBoxAndToggleBackground: "#c7222a",
    headerTopQuotesPage: "137px",
    scrollHeight: 137,
    toasterTop: "210px",
    alertTop: "205px",
  },
  BackButton: {
    backButtonTop: "190px",
    backButtonTopMobile: "160px",
  },

  //
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px #c7222a",
  },
  reactCalendar: {
    selected: "#C7222A",
    background: "#C7222A",
  },
  leadPageBtn: {
    background: "#C7222A",
    background1: "#C7222A",
    background3: "#ffe2da",
    link: "text-danger",
    textColor: "white",
    borderRadius: "8px",
  },
  Registration: {
    proceedBtn: {
      background: "#C7222A",
      color: "white",
    },
    otherBtn: {
      hex2: "#C7222A",
      hex1: "#C7222A",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "invert(42%) sepia(93%) saturate(1352%) hue-rotate(324deg) brightness(90%) contrast(119%)",
  },
  Stepper: {
    stepperColor: {
      background: "#C7222A",
    },
    linkColor: "text-danger",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #C7222A",
    border: "2px solid #C7222A",
    color: "#C7222A !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#c7222a !important",
    border: "1px solid #c7222a !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#C7222A 0%,#ffffcc 100%)",
    Button: {
      hex2: "#C7222A",
      hex1: "#C7222A",
    },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#C7222A",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#C7222A",
  },
  MultiSelect: {
    color: "#909090",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#c7222a",
    borderBottom: "1px solid #c7222a",
    iconsColor: "#c7222a",
    borderHeader: "5px solid #C7222A",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #c7222a)",
  },
  CardPop: {
    background: "#EDC9C6",
    border: "1px solid #EDC9C6",
  },
  NoPlanCard: {
    background: "#EDC9C6",
    border: "2px dotted #EDC9C6",
    border1: "2px solid #EDC9C6",
    background1: "#edc9c645",
  },
  prevPolicy: {
    color1: "#c7222a",
    color2: "#c7222a",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#c7222a",
    color2: "#fff",
    color3: "black",
    filter:
      "invert(97%) sepia(83%) saturate(3866%) hue-rotate(65deg) brightness(80%) contrast(98%)",
    fontFamily: "sans-serif",
    fontWeight: "bold",
    loginBtnColor: "#c7222a !important",
  },
  Home: {
    backgroundColor: "#FFF",
  },
  regularFont: {
    fontFamily: "pfhandbooksans_regular",
  },
  mediumFont: {
    fontFamily: "pfhandbooksans_medium",
  },
  floatButton: {
    floatColor: "#C7222A",
    floatBorder: "1px solid #C7222A",
    whiteColor: "#fff",
  },
  boldBorder: {
    border: "5px solid #C7222A",
    boxShadow: "#c7222a21 0px -50px 36px -28px inset",
  },
  links: {
    color: "rgb(199, 34, 42)",
  },
  primaryColor: {
    color: "rgb(199, 34, 42) !important",
  },
};

//Gram Cover

const Gramcover = {
  proposalCardSummary: {
    background: "linear-gradient(90deg,rgb(26 63 90) 0%,rgb(26 63 90) 100%)",
  },
  proposalCardActive: {
    background:
      "linear-gradient(90deg,rgb(52, 91, 120) 0%,rgb(52, 91, 120) 100%)",
  },
  proposalProceedBtn: {
    hex1: "rgb(52, 91, 120)",
    hex2: "rgb(52, 91, 120)",
  },
  genderProposal: {
    background:
      "radial-gradient(circle,rgb(52, 91, 120) 17%,rgb(52, 91, 120) 85%)",
    boxShadow: "6.994px 5.664px 21px #fffff",
  },
  questionsProposal: {
    color: "rgb(52, 91, 120)",
    toggleBackgroundColor: "rgb(52, 91, 120)",
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "danger",
    linkColor: "rgb(52, 91, 120)",
    iconColor: "rgb(52, 91, 120)",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff;",
    headColor: "rgb(20 116 125)",
    border: " 1px solid rgb(52, 91, 120);",
    liBorder: "2px solid rgb(52, 91, 120);",
  },
  CheckCircle: {
    backgroundImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: "rgb(52, 91, 120)",
    border: " 1px solid rgb(52, 91, 120);",
  },
  CheckBox: {
    color: "#f",
    border: " 1px solid #000",
  },
  Header: {
    color: "rgb(52, 91, 120)",
    border: " 1px solid rgb(52, 91, 120)",
    borderLogo: " 2.5px solid rgb(52, 91, 120)",
    hex1: "rgb(52, 91, 120)",
    hex2: "rgb(52, 91, 120)",
  },
  FilterConatiner: {
    color: "rgb(52, 91, 120)",
    lightColor: "rgb(52, 91, 120)",
    lightBorder: ".5px solid rgb(52, 91, 120)",
    lightBorder1: "1px solid rgb(52, 91, 120)",
    clearAllIcon: "",
    editIconColor: "#000",
    clearAllTextColor: "#000",
  },
  QuoteCard: {
    color: "rgb(52, 91, 120)",
    color2: " #8B151B !important",
    color3: "rgb(52, 91, 120)",
    border: " 1px solid rgb(52, 91, 120)",
    border2: "1px solid  #8B151B !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow:
      "0 8px 25px -5px rgb(52 91 120 / 40%), 0 10px 10px -5px rgb(52 91 120 / 40%)",
    mouseHoverShadow: "0px 0px 8px 0px rgb(52 91 120 / 40%)",
  },
  QuotePopups: {
    color: "rgb(52, 91, 120)",
    border: "1px solid rgb(52, 91, 120)",
    hex1: "rgb(52, 91, 120)",
    hex2: "rgb(52, 91, 120)",
    color2: " rgb(52, 91, 120)",
    color3: " rgb(52, 91, 120) !important",
    border2: "1px solid  rgb(52, 91, 120)",
    border3: "2px solid rgb(52, 91, 120)",
    lg: "-webkit-linear-gradient(-134deg,rgb(52, 91, 120),#d9b7bb)",
    prevpopBorder: "33px solid rgb(52, 91, 120)",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    borderRadius: "5px",
    linkColor: "rgb(52, 91, 120)",
    navColor: " #DA9089 !important",
    shadowCheck:
      "rgba(0, 0, 0, 0.16) 0px 10px 36px 0px, rgba(0, 0, 0, 0.06) 0px 0px 0px 1px",

    fontColor: "white",
    filterPropertyCheckBox:
      "invert(22%) sepia(76%) saturate(2340%) hue-rotate(150deg) brightness(89%) contrast(99%)",
    moneyBackground: " #EDC9C6 !important",
    filterShadow: " 0 9px 13px #cfcfcf",
    checkBoxAndToggleBackground: "rgb(52, 91, 120)",
    headerTopQuotesPage: "137px",
    scrollHeight: 137,
    toasterTop: "210px",
  },
  BackButton: {
    backButtonTop: "190px",
    backButtonTopMobile: "160px",
  },

  //
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px rgb(52, 91, 120)",
  },
  reactCalendar: {
    selected: "rgb(52, 91, 120)",
    background: "rgb(52, 91, 120)",
  },
  leadPageBtn: {
    background: "rgb(52, 91, 120)",
    background1: "rgb(52, 91, 120)",
    background3: "",
    link: "rgb(52, 91, 120)",
    textColor: "white",
    // borderRadius: "8px",
  },
  Registration: {
    proceedBtn: {
      background: "rgb(52, 91, 120)",
      color: "white",
    },
    otherBtn: {
      hex2: "rgb(52, 91, 120)",
      hex1: "rgb(52, 91, 120)",
    },
  },
  VehicleType: {
    buttonVariant: "info",
    outlineVariant: "outline-info",
    filterIconCol:
      "invert(42%) sepia(93%) saturate(1352%) hue-rotate(191deg) brightness(48%) contrast(119%)",
  },
  Stepper: {
    stepperColor: {
      background: "rgb(52, 91, 120)",
    },
    linkColor: "rgb(52, 91, 120)",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px rgb(52, 91, 120)",
    border: "2px solid rgb(52, 91, 120)",
    color: "rgb(52, 91, 120) !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "rgb(52, 91, 120) !important",
    border: "1px solid rgb(52, 91, 120) !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,rgb(52, 91, 120) 0%,#ffffcc 100%)",
    Button: {
      hex2: "rgb(52, 91, 120)",
      hex1: "rgb(52, 91, 120)",
    },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "rgb(52, 91, 120)",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "rgb(52, 91, 120)",
  },
  MultiSelect: {
    color: "#909090",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "rgb(52, 91, 120)",
    borderBottom: "1px solid rgb(52, 91, 120)",
    iconsColor: "rgb(52, 91, 120)",
    borderHeader: "5px solid rgb(52, 91, 120)",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, rgb(52, 91, 120))",
  },
  CardPop: {
    background: "#EDC9C6",
    border: "1px solid #EDC9C6",
  },
  NoPlanCard: {
    background: "#EDC9C6",
    border: "2px dotted #EDC9C6",
    border1: "2px solid #EDC9C6",
    background1: "rgb(52 91 120 / 11%)",
  },
  prevPolicy: {
    color1: "rgb(52, 91, 120)",
    color2: "rgb(52, 91, 120)",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "rgb(52, 91, 120)",
    color2: "#fff",
    color3: "black",
    filter:
      "invert(15%) sepia(96%) saturate(3866%) hue-rotate(155deg) brightness(70%) contrast(50%)",
    fontFamily: "sans-serif",
    fontWeight: "bold",
    loginBtnColor: "rgb(52, 91, 120) !important",
  },
  Home: {
    backgroundColor: "#FFF",
  },
  regularFont: {
    // fontFamily: 'Quicksand',
  },
  mediumFont: {
    // fontFamily: 'Quicksand',
  },
  floatButton: {
    floatColor: "rgb(52, 91, 120)",
    floatBorder: "1px solid rgb(52, 91, 120)",
  },
  boldBorder: {
    border: "5px solid rgb(52, 91, 120)",
    boxShadow: "rgb(52 91 120 / 38%) 0px -50px 36px -28px inset",
  },
  links: {
    color: "rgb(52, 91, 120)",
  },
  primaryColor: {
    color: "rgb(52, 91, 120) !important",
  },
};

const RB = {
  proposalCardSummary: {
    background: `linear-gradient(90deg, #F27F21 0%, #F27F21 100%)`,
  },
  proposalCardActive: {
    background: `linear-gradient(90deg, #F27F21 0%,  #F27F21 100%)`,
  },
  proposalProceedBtn: {
    hex1: "#F27F21",
    hex2: "#F27F21",
  },
  genderProposal: {
    background: `radial-gradient(circle, #F27F21 17%, #F27F21 85%)`,
    boxShadow: "6.994px 5.664px 21px #ff66004a",
  },
  questionsProposal: {
    color: `#F27F21`,
    toggleBackgroundColor: `#F27F21`,
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "dark",
    linkColor: "",
    iconColor: "#F27F21",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #F27F21;",
    liBorder: "2px solid #F27F21;",
  },
  CheckCircle: {
    backgroundImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: `#F27F21`,
    border: "1px solid #F27F21;",
  },
  CheckBox: {
    color: `#fff`,
    border: "1px solid #F27F21",
  },
  Header: {
    color: `#F27F21`,
    border: " 1px solid #F27F21",
    borderLogo: " 2.5px solid #F27F21",
    hex1: "#F27F21",
    hex2: "#F27F21",
  },
  FilterConatiner: {
    color: `#F27F21`,
    lightColor: "#F27F21",
    lightBorder: " .5px solid #F27F21",
    lightBorder1: " .5px solid #F27F21",
    clearAllIcon: "",
    editIconColor: "#0066AF",
    clearAllTextColor: "#fff",
  },
  QuoteCard: {
    color: `#F27F21`,
    color2: " #F27F21 !important",
    color3: "#F27F21",
    border: " 1px solid #F27F21",
    border2: "1px solid  #F27F21 !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #1769B35c, 0 10px 10px -5px #1769B35c",
    mouseHoverShadow: "0px 0px 8px 0px #F27F21",
    ribbonBackground: `linear-gradient(
      90deg,
      rgba(2, 0, 36, 1) 0%,
      rgba(255, 210, 112, 1) 0%,
      rgba(255, 255, 255, 1) 45%,
      rgba(255, 255, 255, 1) 100%
    );`,
  },
  QuotePopups: {
    color: `#F27F21`,
    border: "1px solid #F27F21",
    hex1: "#F27F21",
    hex2: "#F27F21",
    color2: "#F27F21",
    border2: "1px solid  #F27F21",
    border3: "2px solid #F27F21",
    lg: "-webkit-linear-gradient(-134deg,#F27F21,#d9b7bb)",
    prevpopBorder: "33px solid #F27F21",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    navColor: "#F27F21 !important",
    checkBoxAndToggleBackground: "",
    journeyCategoryButtonColor: "#F27F21 !important",
    fontFamily: "lato",
    fontFamilyBold: "lato",
    linkColor: "#F27F21",
  },
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px #F27F21",
  },
  reactCalendar: {
    selected: "#F27F21",
    background: "#F27F21",
  },
  leadPageBtn: {
    background1: "#DD5F41",
    background2: "#E2844A",
    background3: "#ffe2da",
    link: "",
    textColor: "",
    borderRadius: "",
  },
  Registration: {
    proceedBtn: {
      background: `#F27F21`,
      color: "white",
    },
    otherBtn: {
      hex1: "#DD5F41",
      hex2: "#E2844A",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "invert(42%) sepia(93%) saturate(1183%) hue-rotate(347deg) brightness(90%) contrast(111%)",
  },
  Stepper: {
    stepperColor: {
      background: "#F27F21",
    },
    linkColor: "text-primary",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #F27F21",
    border: "2px solid #F27F21",
    color: "#F27F21 !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#F27F21 !important",
    border: "1px solid #F27F21 !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#F27F21 0%,#ffffcc 100%)",
    Button: { hex2: "#F27F21", hex1: "#F27F21" },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#F27F21",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#F27F21",
  },
  MultiSelect: {
    color: "#F27F21",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#F27F21",
    borderBottom: "1px solid #F27F21",
    iconsColor: "#F27F21",
    borderHeader: "5px solid #F27F21",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #F27F21) ",
  },
  CardPop: {
    background: "#1769B31c",
    border: "1px solid #F27F21",
  },
  NoPlanCard: {
    background: "#1769B31c",
    background1: "#f27f211c",
    border: "2px dotted #F27F21",
    border1: "2px solid #F27F21",
  },
  prevPolicy: {
    color1: "#F27F21",
    color2: "#F27F21",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#F27F21",
    color2: "#fff",
    color3: "black",
    filter: "grayscale(118%) sepia(81%) hue-rotate(351deg) saturate(26)",
    loginBtnColor: "#F27F21 !important",
  },
  Payment: {
    color: "#F27F21",
  },
  City: {
    color: "#fff",
    background: "#F27F21",
    border: "1px solid #F27F21",
  },
  Home: {
    backgroundColor: "",
  },
  regularFont: {
    headerFontFamily: "pt-serif",
    fontFamily: "lato",
    fontColor: "#0066AF",
    textColor: "#686868",
    fontWeight: "600",
  },
  mediumFont: {
    fontFamily: "lato",
  },
  floatButton: {
    floatColor: "#0066AF",
    floatBorder: "1px solid #0066AF",
  },
  boldBorder: {
    border: "5px solid #F27F21",
    boxShadow: "#ff660059 0px -50px 36px -28px inset",
  },
  headings: {
    fontSize: "21px",
    textColor: "#686868",
  },
  links: {
    color: "rgb(242, 127, 33)",
  },
  primaryColor: {
    color: "#F27F21",
  },
};

const SPA = {
  proposalCardSummary: {
    background: `linear-gradient(90deg, #3877D6 0%, #3877D6 100%)`,
  },
  proposalCardActive: {
    background: `linear-gradient(90deg, #3877D6 0%,  #3877D6 100%)`,
  },
  proposalProceedBtn: {
    hex1: "#3877D6",
    hex2: "#3877D6",
  },
  genderProposal: {
    background: `radial-gradient(circle, #3877D6 17%, #3877D6 85%)`,
    boxShadow: "6.994px 5.664px 21px #3877d64a",
  },
  questionsProposal: {
    color: `#3877D6`,
    toggleBackgroundColor: `#3877D6`,
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "dark",
    linkColor: "",
    iconColor: "#3877D6",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #3877D6;",
    liBorder: "2px solid #3877D6;",
  },
  CheckCircle: {
    backgroundImage: `url(/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: `#3877D6`,
    border: "1px solid #3877D6;",
  },
  CheckBox: {
    color: `#fff`,
    border: "1px solid #3877D6",
  },
  Header: {
    color: `#3877D6`,
    border: " 1px solid #3877D6",
    borderLogo: " 2.5px solid #3877D6",
    hex1: "#3877D6",
    hex2: "#3877D6",
  },
  FilterConatiner: {
    color: `#3877D6`,
    lightColor: "#3877D6",
    lightBorder: " .5px solid #3877D6",
    lightBorder1: " .5px solid #3877D6",
    clearAllIcon: "",
    editIconColor: "#000",
    clearAllTextColor: "#fff",
  },
  QuoteCard: {
    color: `#3877D6`,
    color2: " #3877D6 !important",
    color3: "#3877D6",
    border: " 1px solid #3877D6",
    border2: "1px solid  #3877D6 !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #1769B35c, 0 10px 10px -5px #1769B35c",
    mouseHoverShadow: "0px 0px 8px 0px #3877D6",
    ribbonBackground:
      "linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(56,119,214,1) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 100%)",
  },
  QuotePopups: {
    color: `#3877D6`,
    border: "1px solid #3877D6",
    hex1: "#3877D6",
    hex2: "#3877D6",
    color2: "#3877D6",
    border2: "1px solid  #3877D6",
    border3: "2px solid #3877D6",
    lg: "-webkit-linear-gradient(-134deg,#3877D6,#d9b7bb)",
    prevpopBorder: "33px solid #3877D6",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    navColor: "#3877D6 !important",
    checkBoxAndToggleBackground: "#3877D6",
    journeyCategoryButtonColor: "#3877D6 !important",
    fontFamily: "roboto",
    fontFamilyBold: "roboto",
    linkColor: "#3877D6",
  },
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px #3877D6",
  },
  reactCalendar: {
    selected: "#3877D6",
    background: "#3877D6",
  },
  leadPageBtn: {
    background: "#3877D6",
    background1: "#3877D6",
    background2: "#3877D6",
    background3: "#bbd6ff",
    link: "",
    textColor: "",
    borderRadius: "",
  },
  Registration: {
    proceedBtn: {
      background: `#3877D6`,
      color: "white",
    },
    otherBtn: {
      hex1: "#3877D6",
      hex2: "#3877D6",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "invert(42%) sepia(75%) saturate(1183%) hue-rotate(558deg) brightness(91%) contrast(93%)",
  },
  Stepper: {
    stepperColor: {
      background: "#3877D6",
    },
    linkColor: "text-primary",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #3877D6",
    border: "2px solid #3877D6",
    color: "#3877D6 !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#3877D6 !important",
    border: "1px solid #3877D6 !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#3877D6 0%,#ffffcc 100%)",
    Button: { hex2: "#3877D6", hex1: "#3877D6" },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#3877D6",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#3877D6",
  },
  MultiSelect: {
    color: "#3877D6",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#3877D6",
    borderBottom: "1px solid #3877D6",
    iconsColor: "#3877D6",
    borderHeader: "5px solid #3877D6",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #3877D6) ",
  },
  CardPop: {
    background: "#1769B31c",
    border: "1px solid #3877D6",
  },
  NoPlanCard: {
    background: "#1769B31c",
    background1: "#3877d61c",
    border: "2px dotted #3877D6",
    border1: "2px solid #3877D6",
  },
  prevPolicy: {
    color1: "#3877D6",
    color2: "#3877D6",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#3877D6",
    color2: "#fff",
    color3: "black",
    filter: "grayscale(118%) sepia(64%) hue-rotate(558deg) saturate(26)",
    loginBtnColor: "#3877D6 !important",
  },
  Payment: {
    color: "#3877D6",
  },
  City: {
    color: "#fff",
    background: "#3877D6",
    border: "1px solid #3877D6",
  },
  Home: {
    backgroundColor: "",
  },
  regularFont: {
    headerFontFamily: "Poppins",
    fontFamily: "roboto",
    fontColor: "#000",
    textColor: "#686868",
    fontWeight: "600",
  },
  mediumFont: {
    fontFamily: "roboto",
  },
  floatButton: {
    floatColor: "#0066AF",
    floatBorder: "1px solid #0066AF",
  },
  boldBorder: {
    border: "5px solid #3877D6",
    boxShadow: "#0066af59 0px -50px 36px -28px inset",
  },
  headings: {
    fontSize: "21px",
    textColor: "#686868",
  },
  links: {
    color: "rgb(56, 119, 214)",
  },
  primaryColor: {
    color: "rgb(56, 119, 214) !important",
  },
};

const BAJAJ = {
  proposalCardSummary: {
    background: `linear-gradient(90deg, #D71419 0%, #D71419 100%)`,
  },
  proposalCardActive: {
    background: `linear-gradient(90deg, #ED1C24 0%,  #ED1C24 100%)`,
  },
  proposalProceedBtn: {
    hex1: "#ED1C24",
    hex2: "#ED1C24",
  },
  genderProposal: {
    background: `radial-gradient(circle, #ED1C24 17%, #ED1C24 85%)`,
    boxShadow: "6.994px 5.664px 21px #ED1C244a",
  },
  questionsProposal: {
    color: `#ED1C24`,
    toggleBackgroundColor: `#ED1C24`,
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "dark",
    linkColor: "",
    iconColor: "#ED1C24",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #ED1C24;",
    liBorder: "2px solid #ED1C24;",
  },
  CheckCircle: {
    backgroundImage: `url(/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: `#ED1C24`,
    border: "1px solid #ED1C24;",
  },
  CheckBox: {
    color: `#fff`,
    border: "1px solid #ED1C24",
  },
  Header: {
    color: `#ED1C24`,
    border: " 1px solid #ED1C24",
    borderLogo: " 2.5px solid #ED1C24",
    hex1: "#ED1C24",
    hex2: "#ED1C24",
  },
  FilterConatiner: {
    color: `#1F9DD3`,
    lightColor: "#ED1C24",
    lightBorder: " .5px solid #ED1C24",
    lightBorder1: " .5px solid #ED1C24",
    clearAllIcon: "#1F9DD3",
    editIconColor: "#1F9DD3",
    clearAllTextColor: "#fff",
  },
  QuoteCard: {
    color: `#ED1C24`,
    color2: " #ED1C24 !important",
    color3: "#ED1C24",
    border: " 1px solid #ED1C24",
    border2: "1px solid  #ED1C24 !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #1769B35c, 0 10px 10px -5px #1769B35c",
    mouseHoverShadow: "0px 0px 8px 0px #ED1C24",
    ribbonBackground:
      "linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(237,28,36,1) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 100%)",
  },
  QuotePopups: {
    color: `#ED1C24`,
    border: "1px solid #ED1C24",
    hex1: "#ED1C24",
    hex2: "#ED1C24",
    color2: "#ED1C24",
    border2: "1px solid  #ED1C24",
    border3: "2px solid #ED1C24",
    lg: "-webkit-linear-gradient(-134deg,#ED1C24,#d9b7bb)",
    prevpopBorder: "33px solid #ED1C24",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    navColor: "#ED1C24 !important",
    checkBoxAndToggleBackground: "#ED1C24",
    journeyCategoryButtonColor: "#ED1C24 !important",
    fontFamily: "Montserrat",
    fontFamilyBold: "Montserrat",
    linkColor: "#ED1C24",
  },
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px #ED1C24",
  },
  reactCalendar: {
    selected: "#ED1C24",
    background: "#ED1C24",
  },
  leadPageBtn: {
    background: "#ED1C24",
    background1: "#ED1C24",
    background2: "#ED1C24",
    link: "",
    textColor: "#fff",
    borderRadius: "",
  },
  Registration: {
    proceedBtn: {
      background: `#ED1C24`,
      color: "white",
    },
    otherBtn: {
      hex1: "#ED1C24",
      hex2: "#ED1C24",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "invert(48%) sepia(91%) saturate(1352%) hue-rotate(202deg) brightness(90%) contrast(189%)",
  },
  Stepper: {
    stepperColor: {
      background: "#ED1C24",
    },
    linkColor: "text-primary",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #ED1C24",
    border: "1px solid #ED1C24",
    color: "#ED1C24 !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#ED1C24 !important",
    border: "1px solid #ED1C24 !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#ED1C24 0%,#ffffcc 100%)",
    Button: { hex2: "#ED1C24", hex1: "#ED1C24" },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#ED1C24",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#ED1C24",
  },
  MultiSelect: {
    color: "#ED1C24",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#ED1C24",
    borderBottom: "1px solid #ED1C24",
    iconsColor: "#ED1C24",
    borderHeader: "5px solid #ED1C24",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #ED1C24) ",
  },
  CardPop: {
    background: "#1769B31c",
    border: "1px solid #ED1C24",
  },
  NoPlanCard: {
    background: "#1769B31c",
    background1: "#ed1c241f",
    border: "2px dotted #ED1C24",
    border1: "2px solid #ED1C24",
  },
  prevPolicy: {
    color1: "#ED1C24",
    color2: "#ED1C24",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#ED1C24",
    color2: "#fff",
    color3: "black",
    filter:
      "invert(97%) sepia(83%) saturate(3866%) hue-rotate(65deg) brightness(95%) contrast(98%)",
    loginBtnColor: "#ED1C24 !important",
  },
  Payment: {
    color: "#ED1C24",
  },
  City: {
    color: "#fff",
    background: "#ED1C24",
    border: "1px solid #ED1C24",
  },
  Home: {
    backgroundColor: "",
  },
  regularFont: {
    headerFontFamily: "Montserrat",
    fontFamily: "Montserrat",
    fontColor: "#1F9DD3",
    textColor: "#686868",
    fontWeight: "600",
  },
  mediumFont: {
    fontFamily: "Montserrat",
  },
  fontFamily: "Montserrat",
  floatButton: {
    floatColor: "#1F9DD3",
    floatBorder: "1px solid #1F9DD3",
  },
  boldBorder: {
    border: "5px solid #ED1C24",
    boxShadow: "#ED1C2459 0px -50px 36px -28px inset",
  },
  headings: {
    fontSize: "21px",
    textColor: "#686868",
  },
  links: {
    color: "rgb(56, 119, 214)",
  },
  primaryColor: {
    color: "rgb(56, 119, 214) !important",
  },
};
const KMD = {
  proposalCardSummary: {
    background: `linear-gradient(90deg, #a92e1e 0%, #c93825 100%)`,
  },
  proposalCardActive: {
    background: `linear-gradient(90deg, #812115 0%,  #a92e1e 100%)`,
  },
  proposalProceedBtn: {
    hex1: "#812115",
    hex2: "#812115",
  },
  genderProposal: {
    background: `radial-gradient(circle, #812115 17%, #812115 85%)`,
    boxShadow: "6.994px 5.664px 21px #ED1C244a",
  },
  questionsProposal: {
    color: `#812115`,
    toggleBackgroundColor: `#812115`,
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "dark",
    linkColor: "",
    iconColor: "#812115",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #812115;",
    liBorder: "2px solid #812115;",
  },
  CheckCircle: {
    backgroundImage: `url(/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: `#812115`,
    border: "1px solid #812115;",
  },
  CheckBox: {
    color: `#fff`,
    border: "1px solid #812115",
  },
  Header: {
    color: `#812115`,
    border: " 1px solid #812115",
    borderLogo: " 2.5px solid #812115",
    hex1: "#812115",
    hex2: "#812115",
  },
  FilterConatiner: {
    color: `#d8bfa9`,
    lightColor: "#812115",
    lightBorder: " .5px solid #812115",
    lightBorder1: " .5px solid #812115",
    clearAllIcon: "#d8bfa9",
    editIconColor: "#d8bfa9",
    clearAllTextColor: "#fff",
  },
  QuoteCard: {
    color: `#812115`,
    color2: " #812115 !important",
    color3: "#812115",
    border: " 1px solid #812115",
    border2: "1px solid  #812115 !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #1769B35c, 0 10px 10px -5px #1769B35c",
    mouseHoverShadow: "0px 0px 8px 0px #812115",
    ribbonBackground:
      "linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(237,28,36,1) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 100%)",
  },
  QuotePopups: {
    color: `#812115`,
    border: "1px solid #812115",
    hex1: "#812115",
    hex2: "#812115",
    color2: "#812115",
    border2: "1px solid  #812115",
    border3: "2px solid #812115",
    lg: "-webkit-linear-gradient(-134deg,#812115,#d9b7bb)",
    prevpopBorder: "33px solid #812115",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    navColor: "#812115 !important",
    checkBoxAndToggleBackground: "#812115",
    journeyCategoryButtonColor: "#812115 !important",
    fontFamily: "Montserrat",
    fontFamilyBold: "Montserrat",
    linkColor: "#812115",
  },
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px #812115",
  },
  reactCalendar: {
    selected: "#812115",
    background: "#812115",
  },
  leadPageBtn: {
    background: "#812115",
    background1: "#812115",
    background2: "#812115",
    link: "#812115",
    textColor: "#fff",
    borderRadius: "",
  },
  Registration: {
    proceedBtn: {
      background: `#812115`,
      color: "white",
    },
    otherBtn: {
      hex1: "#812115",
      hex2: "#812115",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "invert(45%) sepia(11%) saturate(1385%) hue-rotate(664deg) brightness(64%) contrast(189%)",
  },
  Stepper: {
    stepperColor: {
      background: "#812115",
    },
    linkColor: "text-primary",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #812115",
    border: "1px solid #812115",
    color: "#812115 !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#812115 !important",
    border: "1px solid #812115 !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#812115 0%,#ffffcc 100%)",
    Button: { hex2: "#812115", hex1: "#812115" },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#812115",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#812115",
  },
  MultiSelect: {
    color: "#812115",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#812115",
    borderBottom: "1px solid #812115",
    iconsColor: "#812115",
    borderHeader: "5px solid #812115",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #812115) ",
  },
  CardPop: {
    background: "#1769B31c",
    border: "1px solid #812115",
  },
  NoPlanCard: {
    background: "#1769B31c",
    background1: "#ed1c241f",
    border: "2px dotted #812115",
    border1: "2px solid #812115",
  },
  prevPolicy: {
    color1: "#812115",
    color2: "#812115",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#812115",
    color2: "#fff",
    color3: "black",
    filter:
      "invert(114%) sepia(85%) saturate(3482%) hue-rotate(-234deg) brightness(120%) contrast(351%)",
    loginBtnColor: "#812115 !important",
  },
  Payment: {
    color: "#812115",
  },
  City: {
    color: "#fff",
    background: "#812115",
    border: "1px solid #812115",
  },
  Home: {
    backgroundColor: "",
  },
  regularFont: {
    headerFontFamily: "Montserrat",
    fontFamily: "Montserrat",
    fontColor: "#d8bfa9",
    textColor: "#686868",
    fontWeight: "600",
  },
  mediumFont: {
    fontFamily: "Montserrat",
  },
  fontFamily: "Montserrat",
  floatButton: {
    floatColor: "#d8bfa9",
    floatBorder: "1px solid #d8bfa9",
  },
  boldBorder: {
    border: "5px solid #812115",
    boxShadow: "#ED1C2459 0px -50px 36px -28px inset",
  },
  headings: {
    fontSize: "21px",
    textColor: "#686868",
  },
  links: {
    color: "#812115",
  },
  primaryColor: {
    color: "rgb(56, 119, 214) !important",
  },
};
const UIB = {
  primary: "#0067b1",
  secondary: "#6a7695",
  proposalCardSummary: {
    background: `linear-gradient(90deg, #4b6bfa  0%, #4b6bfa  100%)`,
  },
  proposalCardActive: {
    background: `linear-gradient(90deg, #0067b1 0%,  #0067b1 100%)`,
  },
  proposalProceedBtn: {
    hex1: "#0067b1",
    hex2: "#0067b1",
  },
  genderProposal: {
    background: `radial-gradient(circle, #0067b1 17%, #0067b1 85%)`,
    boxShadow: "6.994px 5.664px 21px #6a7695",
  },
  questionsProposal: {
    color: `#0067b1`,
    toggleBackgroundColor: `#0067b1`,
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "dark",
    linkColor: "",
    iconColor: "#0067b1",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #0067b1;",
    liBorder: "2px solid #0067b1;",
  },
  CheckCircle: {
    backgroundImage: `url(/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: `#0067b1`,
    border: "1px solid #0067b1;",
  },
  CheckBox: {
    color: `#fff`,
    border: "1px solid #0067b1",
  },
  Header: {
    color: `#0067b1`,
    border: " 1px solid #0067b1",
    borderLogo: " 2.5px solid #0067b1",
    hex1: "#0067b1",
    hex2: "#0067b1",
  },
  FilterConatiner: {
    color: `#0067b1`,
    lightColor: "#0067b1",
    lightBorder: " .5px solid #0067b1",
    lightBorder1: " .5px solid #0067b1",
    clearAllIcon: "#0067b1",
    editIconColor: "#0067b1",
    clearAllTextColor: "#fff",
  },
  QuoteCard: {
    color: `#0067b1`,
    color2: " #0067b1 !important",
    color3: "#0067b1",
    border: " 1px solid #0067b1",
    border2: "1px solid  #0067b1 !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #1769B35c, 0 10px 10px -5px #1769B35c",
    mouseHoverShadow: "0px 0px 8px 0px #0067b1",
    ribbonBackground:
      "linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(0,103,177,1) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 100%)",
  },
  QuotePopups: {
    color: `#0067b1`,
    border: "1px solid #0067b1",
    hex1: "#0067b1",
    hex2: "#0067b1",
    color2: "#0067b1",
    border2: "1px solid  #0067b1",
    border3: "2px solid #0067b1",
    lg: "-webkit-linear-gradient(-134deg,#0067b1,#d9b7bb)",
    prevpopBorder: "33px solid #0067b1",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    navColor: "#0067b1 !important",
    checkBoxAndToggleBackground: "#0067b1",
    journeyCategoryButtonColor: "#0067b1 !important",
    fontFamily: "Arial",
    fontFamilyBold: "Arial",
    linkColor: "#0067b1",
  },
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px #0067b1",
  },
  reactCalendar: {
    selected: "#0067b1",
    background: "#0067b1",
  },
  leadPageBtn: {
    background: "#0067b1",
    background1: "#0067b1",
    background2: "#0067b1",
    background3: "#0067b1",
    link: "",
    textColor: "#fff",
    borderRadius: "",
  },
  Registration: {
    proceedBtn: {
      background: `#0067b1`,
      color: "white",
    },
    otherBtn: {
      hex1: "#0067b1",
      hex2: "#0067b1",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "invert(42%) sepia(93%) saturate(1352%) hue-rotate(589deg) brightness(47%) contrast(119%)",
  },
  Stepper: {
    stepperColor: {
      background: "#0067b1",
    },
    linkColor: "text-primary",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #0067b1",
    border: "2px solid #0067b1",
    color: "#0067b1 !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#0067b1 !important",
    border: "1px solid #0067b1 !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#0067b1 0%,#ffffcc 100%)",
    Button: { hex2: "#0067b1", hex1: "#0067b1" },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#0067b1",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#0067b1",
  },
  MultiSelect: {
    color: "#0067b1",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#0067b1",
    borderBottom: "1px solid #0067b1",
    iconsColor: "#0067b1",
    borderHeader: "5px solid #0067b1",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #0067b1) ",
  },
  CardPop: {
    background: "#1769B31c",
    border: "1px solid #0067b1",
  },
  NoPlanCard: {
    background: "#1769B31c",
    background1: "#0067b11f",
    border: "2px dotted #0067b1",
    border1: "2px solid #0067b1",
  },
  prevPolicy: {
    color1: "#0067b1",
    color2: "#0067b1",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#0067b1",
    color2: "#fff",
    color3: "black",
    filter:
      "invert(97%) sepia(83%) saturate(3866%) hue-rotate(256deg) brightness(95%) contrast(98%)",
    loginBtnColor: "#0067b1 !important",
  },
  Payment: {
    color: "#0067b1",
  },
  City: {
    color: "#fff",
    background: "#0067b1",
    border: "1px solid #0067b1",
  },
  Home: {
    backgroundColor: "",
  },
  regularFont: {
    headerFontFamily: "Arial",
    fontFamily: "Arial",
    fontColor: "#0067b1",
    textColor: "#686868",
    fontWeight: "600",
  },
  mediumFont: {
    fontFamily: "Arial",
  },
  floatButton: {
    floatColor: "#0067b1",
    floatBorder: "1px solid #0067b1",
  },
  boldBorder: {
    border: "5px solid #0067b1",
    boxShadow: "#6a7695 0px -50px 36px -28px inset",
  },
  headings: {
    fontSize: "21px",
    textColor: "#686868",
  },
  links: {
    color: "rgb(0, 103, 177)",
  },
  footer: {
    background: "#e2e1e5",
  },
  primaryColor: "rgb(0, 103, 177) !important",
};

const ACE = {
  proposalCardSummary: {
    background: `linear-gradient(90deg, #32b964 0%, #32b964 100%)`,
  },
  proposalCardActive: {
    background: `linear-gradient(90deg, #23974e 0%,  #23974e 100%)`,
  },
  proposalProceedBtn: {
    hex1: "#23974e",
    hex2: "#23974e",
  },
  genderProposal: {
    background: `radial-gradient(circle, #23974e 17%, #23974e 85%)`,
    boxShadow: "6.994px 5.664px 21px #23974e4a",
  },
  questionsProposal: {
    color: `#23974e`,
    toggleBackgroundColor: `#23974e`,
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "dark",
    linkColor: "",
    iconColor: "#23974e",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #23974e;",
    liBorder: "2px solid #23974e;",
  },
  CheckCircle: {
    backgroundImage: `url(/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: `#23974e`,
    border: "1px solid #23974e;",
  },
  CheckBox: {
    color: `#fff`,
    border: "1px solid #23974e",
  },
  Header: {
    color: `#23974e`,
    border: " 1px solid #23974e",
    borderLogo: " 2.5px solid #23974e",
    hex1: "#23974e",
    hex2: "#23974e",
  },
  FilterConatiner: {
    color: `#0093c7`,
    lightColor: "#23974e",
    lightBorder: " .5px solid #23974e",
    lightBorder1: " .5px solid #23974e",
    clearAllIcon: "#0093c7",
    editIconColor: "#0093c7",
    clearAllTextColor: "#fff",
  },
  QuoteCard: {
    color: `#23974e`,
    color2: " #23974e !important",
    color3: "#23974e",
    border: " 1px solid #23974e",
    border2: "1px solid  #23974e !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #1769B35c, 0 10px 10px -5px #1769B35c",
    mouseHoverShadow: "0px 0px 8px 0px #23974e",
    ribbonBackground:
      "linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(35,151,78,1) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 100%)",
  },
  QuotePopups: {
    color: `#23974e`,
    border: "1px solid #23974e",
    hex1: "#23974e",
    hex2: "#23974e",
    color2: "#23974e",
    border2: "1px solid  #23974e",
    border3: "2px solid #23974e",
    lg: "-webkit-linear-gradient(-134deg,#23974e,#d9b7bb)",
    prevpopBorder: "33px solid #23974e",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    navColor: "#23974e !important",
    checkBoxAndToggleBackground: "#23974e",
    journeyCategoryButtonColor: "#23974e !important",
    fontFamily: "Montserrat",
    fontFamilyBold: "Montserrat",
    linkColor: "#23974e",
  },
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px #23974e",
  },
  reactCalendar: {
    selected: "#23974e",
    background: "#23974e",
  },
  leadPageBtn: {
    background: "#23974e",
    background1: "#23974e",
    background2: "#23974e",
    link: "",
    textColor: "#fff",
    borderRadius: "",
  },
  Registration: {
    proceedBtn: {
      background: `#23974e`,
      color: "white",
    },
    otherBtn: {
      hex1: "#23974e",
      hex2: "#23974e",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "invert(42%) sepia(93%) saturate(1352%) hue-rotate(434deg) brightness(75%) contrast(119%)",
  },
  Stepper: {
    stepperColor: {
      background: "#23974e",
    },
    linkColor: "text-primary",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #23974e",
    border: "2px solid #23974e",
    color: "#23974e !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#23974e !important",
    border: "1px solid #23974e !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#23974e 0%,#ffffcc 100%)",
    Button: { hex2: "#23974e", hex1: "#23974e" },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#23974e",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#23974e",
  },
  MultiSelect: {
    color: "#23974e",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#23974e",
    borderBottom: "1px solid #23974e",
    iconsColor: "#23974e",
    borderHeader: "5px solid #23974e",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #23974e) ",
  },
  CardPop: {
    background: "#1769B31c",
    border: "1px solid #23974e",
  },
  NoPlanCard: {
    background: "#1769B31c",
    background1: "#23974e1c",
    border: "2px dotted #23974e",
    border1: "2px solid #23974e",
  },
  prevPolicy: {
    color1: "#23974e",
    color2: "#23974e",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#23974e",
    color2: "#fff",
    color3: "black",
    filter:
      "invert(97%) sepia(83%) saturate(3866%) hue-rotate(276deg) brightness(54%) contrast(98%)",
    loginBtnColor: "#23974e !important",
  },
  Payment: {
    color: "#23974e",
  },
  City: {
    color: "#fff",
    background: "#23974e",
    border: "1px solid #23974e",
  },
  Home: {
    backgroundColor: "",
  },
  regularFont: {
    headerFontFamily: "Arial",
    fontFamily: "Montserrat",
    fontColor: "#0093c7",
    textColor: "#686868",
    fontWeight: "600",
  },
  mediumFont: {
    fontFamily: "Montserrat",
  },
  floatButton: {
    floatColor: "#0093c7",
    floatBorder: "1px solid #0093c7",
  },
  boldBorder: {
    border: "5px solid #23974e",
    boxShadow: "#23974e59 0px -50px 36px -28px inset",
  },
  headings: {
    fontSize: "21px",
    textColor: "#686868",
  },
  links: {
    color: "rgb(0, 147, 199)",
  },
  primaryColor: "rgb(0, 147, 199) !important",
};

const SRIDHAR = {
  proposalCardSummary: {
    background: `linear-gradient(90deg, #065aa1 0%, #065aa1 100%)`,
  },
  proposalCardActive: {
    background: `linear-gradient(90deg, #003a6b 0%,  #003a6b 100%)`,
  },
  proposalProceedBtn: {
    hex1: "#003a6b",
    hex2: "#003a6b",
  },
  genderProposal: {
    background: `radial-gradient(circle, #003a6b 17%, #003a6b 85%)`,
    boxShadow: "6.994px 5.664px 21px #23974e4a",
  },
  questionsProposal: {
    color: `#003a6b`,
    toggleBackgroundColor: `#003a6b`,
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "dark",
    linkColor: "",
    iconColor: "#003a6b",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #003a6b;",
    liBorder: "2px solid #003a6b;",
  },
  CheckCircle: {
    backgroundImage: `url(/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: `#003a6b`,
    border: "1px solid #003a6b;",
  },
  CheckBox: {
    color: `#fff`,
    border: "1px solid #003a6b",
  },
  Header: {
    color: `#003a6b`,
    border: " 1px solid #003a6b",
    borderLogo: " 2.5px solid #003a6b",
    hex1: "#003a6b",
    hex2: "#003a6b",
  },
  FilterConatiner: {
    color: `#00a5e9`,
    lightColor: "#003a6b",
    lightBorder: " .5px solid #003a6b",
    lightBorder1: " .5px solid #003a6b",
    clearAllIcon: "#00a5e9",
    editIconColor: "#00a5e9",
    clearAllTextColor: "#fff",
  },
  QuoteCard: {
    color: `#003a6b`,
    color2: " #003a6b !important",
    color3: "#003a6b",
    border: " 1px solid #003a6b",
    border2: "1px solid  #003a6b !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #1769B35c, 0 10px 10px -5px #1769B35c",
    mouseHoverShadow: "0px 0px 8px 0px #003a6b",
    ribbonBackground:
      "linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(0,58,107,1) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 100%)",
  },
  QuotePopups: {
    color: `#003a6b`,
    border: "1px solid #003a6b",
    hex1: "#003a6b",
    hex2: "#003a6b",
    color2: "#003a6b",
    border2: "1px solid  #003a6b",
    border3: "2px solid #003a6b",
    lg: "-webkit-linear-gradient(-134deg,#003a6b,#d9b7bb)",
    prevpopBorder: "33px solid #003a6b",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    navColor: "#003a6b !important",
    checkBoxAndToggleBackground: "#003a6b",
    journeyCategoryButtonColor: "#003a6b !important",
    fontFamily: "Montserrat",
    fontFamilyBold: "Montserrat",
    linkColor: "#003a6b",
  },
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px #003a6b",
  },
  reactCalendar: {
    selected: "#003a6b",
    background: "#003a6b",
  },
  leadPageBtn: {
    background: "#003a6b",
    background1: "#003a6b",
    background2: "#003a6b",
    link: "",
    textColor: "#fff",
    borderRadius: "",
  },
  Registration: {
    proceedBtn: {
      background: `#003a6b`,
      color: "white",
    },
    otherBtn: {
      hex1: "#003a6b",
      hex2: "#003a6b",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "invert(42%) sepia(93%) saturate(1352%) hue-rotate(434deg) brightness(75%) contrast(119%)",
  },
  Stepper: {
    stepperColor: {
      background: "#003a6b",
    },
    linkColor: "text-primary",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #003a6b",
    border: "2px solid #003a6b",
    color: "#003a6b !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#003a6b !important",
    border: "1px solid #003a6b !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#003a6b 0%,#ffffcc 100%)",
    Button: { hex2: "#003a6b", hex1: "#003a6b" },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#003a6b",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#003a6b",
  },
  MultiSelect: {
    color: "#003a6b",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#003a6b",
    borderBottom: "1px solid #003a6b",
    iconsColor: "#003a6b",
    borderHeader: "5px solid #003a6b",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #003a6b) ",
  },
  CardPop: {
    background: "#1769B31c",
    border: "1px solid #003a6b",
  },
  NoPlanCard: {
    background: "#1769B31c",
    background1: "#003a6b24",
    border: "2px dotted #003a6b",
    border1: "2px solid #003a6b",
  },
  prevPolicy: {
    color1: "#003a6b",
    color2: "#003a6b",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#003a6b",
    color2: "#fff",
    color3: "black",
    filter:
      "invert(97%) sepia(83%) saturate(3866%) hue-rotate(276deg) brightness(54%) contrast(98%)",
    loginBtnColor: "#003a6b !important",
  },
  Payment: {
    color: "#003a6b",
  },
  City: {
    color: "#fff",
    background: "#003a6b",
    border: "1px solid #003a6b",
  },
  Home: {
    backgroundColor: "",
  },
  regularFont: {
    headerFontFamily: "Arial",
    fontFamily: "Montserrat",
    fontColor: "#00a5e9",
    textColor: "#686868",
    fontWeight: "600",
  },
  mediumFont: {
    fontFamily: "Montserrat",
  },
  floatButton: {
    floatColor: "#00a5e9",
    floatBorder: "1px solid #00a5e9",
  },
  boldBorder: {
    border: "5px solid #003a6b",
    boxShadow: "#23974e59 0px -50px 36px -28px inset",
  },
  headings: {
    fontSize: "21px",
    textColor: "#686868",
  },
  links: {
    color: "rgb(0, 147, 199)",
  },
  primaryColor: "rgb(0, 147, 199) !important",
};

const POLICYERA = {
  proposalCardSummary: {
    background: `linear-gradient(90deg, #81B4CA 0%, #81B4CA 100%)`,
  },
  proposalCardActive: {
    background: `linear-gradient(90deg, #71B5CD 0%,  #71B5CD 100%)`,
  },
  proposalProceedBtn: {
    hex1: "#71B5CD",
    hex2: "#71B5CD",
  },
  genderProposal: {
    background: `radial-gradient(circle, #71B5CD 17%, #71B5CD 85%)`,
    boxShadow: "6.994px 5.664px 21px #23974e4a",
  },
  questionsProposal: {
    color: `#71B5CD`,
    toggleBackgroundColor: `#71B5CD`,
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "dark",
    linkColor: "",
    iconColor: "#71B5CD",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #71B5CD;",
    liBorder: "2px solid #71B5CD;",
  },
  CheckCircle: {
    backgroundImage: `url(/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: `#71B5CD`,
    border: "1px solid #71B5CD;",
  },
  CheckBox: {
    color: `#fff`,
    border: "1px solid #71B5CD",
  },
  Header: {
    color: `#71B5CD`,
    border: " 1px solid #71B5CD",
    borderLogo: " 2.5px solid #71B5CD",
    hex1: "#71B5CD",
    hex2: "#71B5CD",
  },
  FilterConatiner: {
    color: `#2a4072`,
    lightColor: "#71B5CD",
    lightBorder: " .5px solid #71B5CD",
    lightBorder1: " .5px solid #71B5CD",
    clearAllIcon: "#2a4072",
    editIconColor: "#2a4072",
    clearAllTextColor: "#fff",
  },
  QuoteCard: {
    color: `#71B5CD`,
    color2: " #71B5CD !important",
    color3: "#71B5CD",
    border: " 1px solid #71B5CD",
    border2: "1px solid  #71B5CD !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #1769B35c, 0 10px 10px -5px #1769B35c",
    mouseHoverShadow: "0px 0px 8px 0px #71B5CD",
    ribbonBackground:
      "linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(113,181,205,1) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 100%)",
  },
  QuotePopups: {
    color: `#71B5CD`,
    border: "1px solid #71B5CD",
    hex1: "#71B5CD",
    hex2: "#71B5CD",
    color2: "#71B5CD",
    border2: "1px solid  #71B5CD",
    border3: "2px solid #71B5CD",
    lg: "-webkit-linear-gradient(-134deg,#71B5CD,#d9b7bb)",
    prevpopBorder: "33px solid #71B5CD",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    navColor: "#71B5CD !important",
    checkBoxAndToggleBackground: "#71B5CD",
    journeyCategoryButtonColor: "#71B5CD !important",
    fontFamily: "Poppins",
    fontFamilyBold: "Poppins",
    linkColor: "#71B5CD",
  },
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px #71B5CD",
  },
  reactCalendar: {
    selected: "#71B5CD",
    background: "#71B5CD",
  },
  leadPageBtn: {
    background: "#71B5CD",
    background1: "#71B5CD",
    background2: "#71B5CD",
    link: "",
    textColor: "#fff",
    borderRadius: "",
  },
  Registration: {
    proceedBtn: {
      background: `#71B5CD`,
      color: "white",
    },
    otherBtn: {
      hex1: "#71B5CD",
      hex2: "#71B5CD",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "invert(42%) sepia(93%) saturate(1352%) hue-rotate(434deg) brightness(75%) contrast(119%)",
  },
  Stepper: {
    stepperColor: {
      background: "#71B5CD",
    },
    linkColor: "text-primary",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #71B5CD",
    border: "2px solid #71B5CD",
    color: "#71B5CD !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#71B5CD !important",
    border: "1px solid #71B5CD !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#71B5CD 0%,#ffffcc 100%)",
    Button: { hex2: "#71B5CD", hex1: "#71B5CD" },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#71B5CD",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#71B5CD",
  },
  MultiSelect: {
    color: "#71B5CD",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#71B5CD",
    borderBottom: "1px solid #71B5CD",
    iconsColor: "#71B5CD",
    borderHeader: "5px solid #71B5CD",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #71B5CD) ",
  },
  CardPop: {
    background: "#1769B31c",
    border: "1px solid #71B5CD",
  },
  NoPlanCard: {
    background: "#1769B31c",
    background1: "#71b5cd1c",
    border: "2px dotted #71B5CD",
    border1: "2px solid #71B5CD",
  },
  prevPolicy: {
    color1: "#71B5CD",
    color2: "#71B5CD",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#71B5CD",
    color2: "#fff",
    color3: "black",
    filter:
      "invert(97%) sepia(83%) saturate(3866%) hue-rotate(281deg) brightness(200%) contrast(115%)",
    loginBtnColor: "#71B5CD !important",
  },
  Payment: {
    color: "#71B5CD",
  },
  City: {
    color: "#fff",
    background: "#71B5CD",
    border: "1px solid #71B5CD",
  },
  Home: {
    backgroundColor: "",
  },
  regularFont: {
    headerFontFamily: "Manrope",
    fontFamily: "Poppins",
    fontColor: "#2a4072",
    textColor: "#686868",
    fontWeight: "600",
  },
  mediumFont: {
    fontFamily: "Poppins",
  },
  floatButton: {
    floatColor: "#2a4072",
    floatBorder: "1px solid #2a4072",
  },
  boldBorder: {
    border: "5px solid #71B5CD",
    boxShadow: "#23974e59 0px -50px 36px -28px inset",
  },
  headings: {
    fontSize: "21px",
    textColor: "#686868",
  },
  links: {
    color: "rgb(0, 147, 199)",
  },
  primaryColor: "rgb(0, 147, 199) !important",
};
const HEROCARE = {
  proposalCardSummary: {
    background: `linear-gradient(90deg, #81B4CA 0%, #81B4CA 100%)`,
  },
  proposalCardActive: {
    background: `linear-gradient(90deg, #ed3237 0%,  #ed3237 100%)`,
  },
  proposalProceedBtn: {
    hex1: "#ed3237",
    hex2: "#ed3237",
  },
  genderProposal: {
    background: `radial-gradient(circle, #ed3237 17%, #ed3237 85%)`,
    boxShadow: "6.994px 5.664px 21px #23974e4a",
  },
  questionsProposal: {
    color: `#ed3237`,
    toggleBackgroundColor: `#ed3237`,
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "dark",
    linkColor: "",
    iconColor: "#ed3237",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #ed3237;",
    liBorder: "2px solid #ed3237;",
  },
  CheckCircle: {
    backgroundImage: `url(/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: `#ed3237`,
    border: "1px solid #ed3237;",
  },
  CheckBox: {
    color: `#fff`,
    border: "1px solid #ed3237",
  },
  Header: {
    color: `#ed3237`,
    border: " 1px solid #ed3237",
    borderLogo: " 2.5px solid #ed3237",
    hex1: "#ed3237",
    hex2: "#ed3237",
  },
  FilterConatiner: {
    color: `#2a4072`,
    lightColor: "#ed3237",
    lightBorder: " .5px solid #ed3237",
    lightBorder1: " .5px solid #ed3237",
    clearAllIcon: "#2a4072",
    editIconColor: "#2a4072",
    clearAllTextColor: "#fff",
  },
  QuoteCard: {
    color: `#ed3237`,
    color2: " #ed3237 !important",
    color3: "#ed3237",
    border: " 1px solid #ed3237",
    border2: "1px solid  #ed3237 !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #1769B35c, 0 10px 10px -5px #1769B35c",
    mouseHoverShadow: "0px 0px 8px 0px #ed3237",
    ribbonBackground:
      "linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(113,181,205,1) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 100%)",
  },
  QuotePopups: {
    color: `#ed3237`,
    border: "1px solid #ed3237",
    hex1: "#ed3237",
    hex2: "#ed3237",
    color2: "#ed3237",
    border2: "1px solid  #ed3237",
    border3: "2px solid #ed3237",
    lg: "-webkit-linear-gradient(-134deg,#ed3237,#d9b7bb)",
    prevpopBorder: "33px solid #ed3237",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    navColor: "#ed3237 !important",
    checkBoxAndToggleBackground: "#ed3237",
    journeyCategoryButtonColor: "#ed3237 !important",
    fontFamily: "Poppins",
    fontFamilyBold: "Poppins",
    linkColor: "#ed3237",
    fontColor: "#ffffff",
  },
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px #ed3237",
  },
  reactCalendar: {
    selected: "#ed3237",
    background: "#ed3237",
  },
  leadPageBtn: {
    background: "#ed3237",
    background1: "#ed3237",
    background2: "#ed3237",
    link: "",
    textColor: "#fff",
    borderRadius: "",
  },
  Registration: {
    proceedBtn: {
      background: `#ed3237`,
      color: "white",
    },
    otherBtn: {
      hex1: "#ed3237",
      hex2: "#ed3237",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "brightness(0) saturate(100%) invert(40%) sepia(81%) saturate(5722%) hue-rotate(202deg) brightness(106%) contrast(104%)",
  },
  Stepper: {
    stepperColor: {
      background: "#ed3237",
    },
    linkColor: "text-primary",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #ed3237",
    border: "2px solid #ed3237",
    color: "#ed3237 !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#ed3237 !important",
    border: "1px solid #ed3237 !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#ed3237 0%,#ffffcc 100%)",
    Button: { hex2: "#ed3237", hex1: "#ed3237" },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#ed3237",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#ed3237",
  },
  MultiSelect: {
    color: "#ed3237",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#ed3237",
    borderBottom: "1px solid #ed3237",
    iconsColor: "#ed3237",
    borderHeader: "5px solid #ed3237",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #ed3237) ",
  },
  CardPop: {
    background: "#1769B31c",
    border: "1px solid #ed3237",
  },
  NoPlanCard: {
    background: "#1769B31c",
    background1: "#71b5cd1c",
    border: "2px dotted #ed3237",
    border1: "2px solid #ed3237",
  },
  prevPolicy: {
    color1: "#ed3237",
    color2: "#ed3237",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#ed3237",
    color2: "#fff",
    color3: "black",
    filter:
      "invert(97%) sepia(83%) saturate(3866%) hue-rotate(281deg) brightness(200%) contrast(115%)",
    loginBtnColor: "#ed3237 !important",
  },
  Payment: {
    color: "#ed3237",
  },
  City: {
    color: "#fff",
    background: "#ed3237",
    border: "1px solid #ed3237",
  },
  Home: {
    backgroundColor: "",
  },
  regularFont: {
    headerFontFamily: "Manrope",
    fontFamily: "Poppins",
    fontColor: "#2a4072",
    textColor: "#686868",
    fontWeight: "600",
  },
  mediumFont: {
    fontFamily: "Poppins",
  },
  floatButton: {
    floatColor: "#2a4072",
    floatBorder: "1px solid #2a4072",
  },
  boldBorder: {
    border: "5px solid #ed3237",
    boxShadow: "#ff610059 0px -50px 36px -28px inset",
  },
  headings: {
    fontSize: "21px",
    textColor: "#686868",
  },
  links: {
    color: "rgb(0, 147, 199)",
  },
  primaryColor: "rgb(0, 147, 199) !important",
};

const TATA = {
  fontFamily: "roboto",
  proposalCardSummary: {
    background: `linear-gradient(90deg, #0489d7 0%,#8230d9 100%)`,
  },
  proposalCardActive: {
    background: `linear-gradient(90deg, #0099f2 0%, #9a39fe 100%)`,
  },
  proposalProceedBtn: {
    hex1: "#0099f2",
    hex2: "#0099f2",
  },
  genderProposal: {
    background: `radial-gradient(circle, #0099f2 17%, #0099f2 85%)`,
    boxShadow: "6.994px 5.664px 21px #23974e4a",
  },
  questionsProposal: {
    color: `#0099f2`,
    toggleBackgroundColor: `#0099f2`,
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "dark",
    linkColor: "",
    iconColor: "#0099f2",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #0099f2;",
    liBorder: "2px solid #0099f2;",
  },
  CheckCircle: {
    backgroundImage: `url(/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: `#0099f2`,
    border: "1px solid #0099f2;",
  },
  CheckBox: {
    color: `#fff`,
    border: "1px solid #0099f2",
  },
  Header: {
    color: `#0099f2`,
    border: " 1px solid #0099f2",
    borderLogo: " 2.5px solid #0099f2",
    hex1: "#0099f2",
    hex2: "#0099f2",
  },
  FilterConatiner: {
    color: `#4b6af8`,
    lightColor: "#0099f2",
    lightBorder: " .5px solid #0099f2",
    lightBorder1: " .5px solid #0099f2",
    clearAllIcon: "#4b6af8",
    editIconColor: "#4b6af8",
    clearAllTextColor: "#fff",
  },
  QuoteCard: {
    color: `#0099f2`,
    color2: " #0099f2 !important",
    color3: "#0099f2",
    border: " 1px solid #0099f2",
    border2: "1px solid  #0099f2 !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #1769B35c, 0 10px 10px -5px #1769B35c",
    mouseHoverShadow: "0px 0px 8px 0px #0099f2",
    ribbonBackground:
      "linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(0,153,242,1) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 100%)",
  },
  QuotePopups: {
    color: `#0099f2`,
    border: "1px solid #0099f2",
    hex1: "#0099f2",
    hex2: "#0099f2",
    color2: "#0099f2",
    border2: "1px solid  #0099f2",
    border3: "2px solid #0099f2",
    lg: "-webkit-linear-gradient(-134deg,#0099f2,#d9b7bb)",
    prevpopBorder: "33px solid #0099f2",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    navColor: "#0099f2 !important",
    checkBoxAndToggleBackground: "#0099f2",
    journeyCategoryButtonColor: "#0099f2 !important",
    fontFamily: "roboto",
    fontFamilyBold: "roboto",
    linkColor: "#0099f2",
  },
  ReviewCard: {
    color: "#007bff;",
    border: " 1px solid #007bff;",
    borderDashed: " 1px dashed #007bff;",
    color2: "#007bff;",
  },
  avatar: {
    border: "solid 2px #0099f2",
  },
  reactCalendar: {
    selected: "#0099f2",
    background: "#0099f2",
  },
  leadPageBtn: {
    background: "#0099f2",
    background1: "#0099f2",
    background2: "#0099f2",
    link: "",
    textColor: "#fff",
    borderRadius: "",
  },
  Registration: {
    proceedBtn: {
      background: `#0099f2`,
      color: "white",
    },
    otherBtn: {
      hex1: "#0099f2",
      hex2: "#0099f2",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "invert(42%) sepia(93%) saturate(1352%) hue-rotate(183deg) brightness(93%) contrast(119%)",
  },
  Stepper: {
    stepperColor: {
      background: "#0099f2",
    },
    linkColor: "text-primary",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #0099f2",
    border: "2px solid #0099f2",
    color: "#0099f2 !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#0099f2 !important",
    border: "1px solid #0099f2 !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#0099f2 0%,#ffffcc 100%)",
    Button: { hex2: "#0099f2", hex1: "#0099f2" },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#0099f2",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#0099f2",
  },
  MultiSelect: {
    color: "#0099f2",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#0099f2",
    borderBottom: "1px solid #0099f2",
    iconsColor: "#0099f2",
    borderHeader: "5px solid #0099f2",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #0099f2) ",
  },
  CardPop: {
    background: "#1769B31c",
    border: "1px solid #0099f2",
  },
  NoPlanCard: {
    background: "#1769B31c",
    background1: "#0099f214",
    border: "2px dotted #0099f2",
    border1: "2px solid #0099f2",
  },
  prevPolicy: {
    color1: "#0099f2",
    color2: "#0099f2",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#0099f2",
    color2: "#fff",
    color3: "black",
    // filter: "grayscale(100%) sepia(100%) hue-rotate(318deg) saturate(5)",
    // filter: "grayscale(100%) sepia(100%) hue-rotate(663deg) saturate(23)",
    // filter: "grayscale(100%) sepia(72%) hue-rotate(552deg) saturate(115)",
    filter:
      "invert(97%) sepia(83%) saturate(3866%) hue-rotate(656deg) brightness(200%) contrast(115%)",
    loginBtnColor: "#0099f2 !important",
  },
  Payment: {
    color: "#0099f2",
    fontFamily: "roboto",
  },
  City: {
    color: "#fff",
    background: "#0099f2",
    border: "1px solid #0099f2",
  },
  Home: {
    backgroundColor: "",
  },
  regularFont: {
    headerFontFamily: "roboto",
    fontFamily: "roboto",
    fontColor: "#4b6af8",
    textColor: "#686868",
    fontWeight: "600",
  },
  mediumFont: {
    fontFamily: "roboto",
  },
  floatButton: {
    floatColor: "#4b6af8",
    floatBorder: "1px solid #4b6af8",
  },
  boldBorder: {
    border: "5px solid #0099f2",
    boxShadow: "#23974e59 0px -50px 36px -28px inset",
  },
  headings: {
    fontSize: "21px",
    textColor: "#686868",
  },
  links: {
    color: "rgb(75, 106, 248)",
  },
  primaryColor: "rgb(0, 147, 199) !important",
};

const PAYTM = {
  proposalCardSummary: {
    background: `linear-gradient(90deg, #00B8F5 0%, #00B8F5 100%)`,
  },
  proposalCardActive: {
    background: `linear-gradient(90deg, #00B8F5 0%,  #00B8F5 100%)`,
  },
  proposalProceedBtn: {
    hex1: "#00B8F5",
    hex2: "#00B8F5",
  },
  genderProposal: {
    background: `radial-gradient(circle, #00B8F5 17%, #00B8F5 85%)`,
    boxShadow: "6.994px 5.664px 21px #0093ff59",
  },
  questionsProposal: {
    color: `#00B8F5`,
    toggleBackgroundColor: `#00B8F5`,
  },
  sideCardProposal: {
    icon: "text-danger",
    badge: "danger",
    editBadge: "dark",
    linkColor: "",
    iconColor: "#00B8F5",
  },
  Button: {
    default: {
      background: "#3fd49f",
      border_color: "#1ad2a4",
      text_color: "#fff",
    },
    danger: {
      background: "#ff8983",
      border_color: "#ff8683",
      text_color: "#fff",
    },
    warning: {
      background: "#eebb4d",
      border_color: "#eebb4d",
      text_color: "#fff",
    },
    outline: {
      background: "#fff",
      border_color: "#cb68d9",
      text_color: "#b406cc",
    },
    square_outline: {
      background: "#fff",
      border_color: "#CE93D8",
      text_color: "#000000",
    },
    outline_secondary: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    submit_disabled: {
      background: "#efefef",
      border_color: "#606060",
      text_color: "#606060",
    },
    outline_solid: {
      background1: "#0084f4",
      background2: "#00c48c",
      border_color: "#D0D0D0",
      text_color: "#fff",
    },
  },
  Products: {
    color: "#007bff",
    headColor: "rgb(20 116 125)",
    border: " 1px solid #00B8F5;",
    liBorder: "2px solid #00B8F5;",
  },
  CheckCircle: {
    backgroundImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/check-blue-circle.svg)`,
  },
  Tab: {
    color: `#00B8F5`,
    border: "1px solid #00B8F5;",
  },
  CheckBox: {
    color: `#fff`,
    border: "1px solid #00B8F5",
  },
  Header: {
    color: `#00B8F5`,
    border: " 1px solid #00B8F5",
    borderLogo: " 2.5px solid #00B8F5",
    hex1: "#00B8F5",
    hex2: "#00B8F5",
  },
  FilterConatiner: {
    color: `#00B8F5`,
    lightColor: "#00B8F5",
    lightBorder: " .5px solid #00B8F5",
    lightBorder1: " .5px solid #00B8F5",
    clearAllIcon: "",
    editIconColor: "#233266",
    clearAllTextColor: "#fff",
  },
  QuoteCard: {
    color: `#00B8F5`,
    color2: " #00B8F5 !important",
    color3: "#00B8F5",
    border: " 1px solid #00B8F5",
    border2: "1px solid  #00B8F5 !important",
    borderCheckBox: "1px solid #62636a",
    boxShadow: "0 8px 25px -5px #1769B35c, 0 10px 10px -5px #1769B35c",
    mouseHoverShadow: "0px 0px 8px 0px #00B8F5",
    ribbonBackground: `linear-gradient(
        90deg,
        rgba(2, 0, 36, 1) 0%,
        rgba(255, 210, 112, 1) 0%,
        rgba(255, 255, 255, 1) 45%,
        rgba(255, 255, 255, 1) 100%
      );`,
  },
  QuotePopups: {
    color: `#00B8F5`,
    border: "1px solid #00B8F5",
    hex1: "#00B8F5",
    hex2: "#00B8F5",
    color2: "#00B8F5",
    border2: "1px solid  #00B8F5",
    border3: "2px solid #00B8F5",
    lg: "-webkit-linear-gradient(-134deg,#00B8F5,#d9b7bb)",
    prevpopBorder: "33px solid #00B8F5",
  },
  //new changes for quotes
  QuoteBorderAndFont: {
    navColor: "#00B8F5 !important",
    checkBoxAndToggleBackground: "",
    journeyCategoryButtonColor: "#00B8F5 !important",
    fontFamily: "San Francisco",
    fontFamilyBold: "San Francisco",
    linkColor: "#00B8F5",
  },
  ReviewCard: {
    color: "#00B8F5;",
    border: " 1px solid #00B8F5;",
    borderDashed: " 1px dashed #00B8F5",
    color2: "#00B8F5;",
  },
  avatar: {
    border: "solid 2px #00B8F5",
  },
  reactCalendar: {
    selected: "#00B8F5",
    background: "#00B8F5",
  },
  leadPageBtn: {
    background: "#00B8F5",
    background1: "#00B8F5",
    background2: "#00B8F5",
    background3: "#ffe2da",
    link: "",
    textColor: "#fff",
    borderRadius: "",
  },
  Registration: {
    proceedBtn: {
      background: `#00B8F5`,
      color: "white",
    },
    otherBtn: {
      hex1: "#00B8F5",
      hex2: "#00B8F5",
    },
  },
  VehicleType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol:
      "invert(8%) sepia(0%) saturate(887%) hue-rotate(378deg) brightness(100%) contrast(135%)",
  },
  Stepper: {
    stepperColor: {
      background: "#00B8F5",
    },
    linkColor: "#233266",
  },
  Tile: {
    boxShadow: "0px 0px 7px 0px #00B8F5",
    border: "2px solid #00B8F5",
    color: "#00B8F5 !important",
  },
  VehicleProceed: {
    hex1: "#054f6c",
    hex2: "#085572",
  },
  journeyType: {
    buttonVariant: "danger",
    outlineVariant: "outline-danger",
    filterIconCol: "none",
  },
  toggleModal: {
    color: "#033500",
    walletImage: `url(${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""
    }/assets/images/wallet-1.svg)`,
  },
  comparePage: {
    color: "#00B8F5 !important",
    border: "1px solid #00B8F5 !important",
    textColor: "#fff",
  },
  paymentConfirmation: {
    headers: "linear-gradient(81.67deg,#00B8F5 0%,#ffffcc 100%)",
    Button: { hex2: "#00B8F5", hex1: "#00B8F5" },
    table: "danger",
    buttonTextColor: "white",
  },
  CallUs: {
    icon: "#00B8F5",
  },
  PaymentStatus: {
    hex1: "#fb7550",
    hex2: "#00B8F5",
  },
  MultiSelect: {
    color: "#00B8F5",
  },
  proposalHeader: {
    color: "black",
  },
  comparePage2: {
    background: "#00B8F5",
    borderBottom: "1px solid #00B8F5",
    iconsColor: "#00B8F5",
    borderHeader: "5px solid #00B8F5",
    lg: "-webkit-linear-gradient(-134deg, #ffffff, #00B8F5) ",
  },
  CardPop: {
    background: "#1769B31c",
    border: "1px solid #00B8F5",
  },
  NoPlanCard: {
    background: "#1769B31c",
    background1: "#f27f211c",
    border: "2px dotted #00B8F5",
    border1: "2px solid #00B8F5",
  },
  prevPolicy: {
    color1: "#00B8F5",
    color2: "#00B8F5",
    boxShadow: "0 0 0 0.2rem rgb(225 83 97 / 50%)",
  },
  LandingPage: {
    color: "#00B8F5",
    color2: "#fff",
    color3: "black",
    filter: "grayscale(86%) sepia(189%) hue-rotate(480deg) saturate(72)",
    loginBtnColor: "#00B8F5 !important",
  },
  Payment: {
    color: "#00B8F5",
  },
  City: {
    color: "#fff",
    background: "#00B8F5",
    border: "1px solid #00B8F5",
  },
  Home: {
    backgroundColor: "",
  },
  regularFont: {
    headerFontFamily: "pt-serif",
    fontFamily: "San Francisco",
    fontColor: "#233266",
    textColor: "#686868",
    fontWeight: "600",
  },
  mediumFont: {
    fontFamily: "San Francisco",
  },
  floatButton: {
    floatColor: "#233266",
    floatBorder: "1px solid #233266",
  },
  boldBorder: {
    border: "5px solid #00B8F5",
    boxShadow: "#0093ff59 0px -50px 36px -28px inset",
  },
  headings: {
    fontSize: "21px",
    textColor: "#686868",
  },
  links: {
    color: "#00B8F5",
  },
  primaryColor: {
    color: "#00B8F5 !important",
  },
};

export default import.meta.env.VITE_BROKER === "ABIBL"
  ? { ...ABIBL }
  : import.meta.env.VITE_BROKER === "Gram-cover"
  ? { ...Gramcover }
  : import.meta.env.VITE_BROKER === "RB"
  ? { ...RB }
  : import.meta.env.VITE_BROKER === "SPA"
  ? { ...SPA }
  : import.meta.env.VITE_BROKER === "BAJAJ"
  ? { ...BAJAJ }
  : import.meta.env.VITE_BROKER === "UIB"
  ? { ...UIB }
  : import.meta.env.VITE_BROKER === "ACE"
  ? { ...ACE }
  : import.meta.env.VITE_BROKER === "SRIDHAR"
  ? { ...SRIDHAR }
  : import.meta.env.VITE_BROKER === "POLICYERA"
  ? { ...POLICYERA }
  : import.meta.env.VITE_BROKER === "TATA"
  ? { ...TATA }
  : import.meta.env.VITE_BROKER === "KMD"
  ? { ...KMD }
  : import.meta.env.VITE_BROKER === "PAYTM"
  ? { ...PAYTM }
  : import.meta.env.VITE_BROKER === "HEROCARE"
  ? { ...HEROCARE }
  : {};
