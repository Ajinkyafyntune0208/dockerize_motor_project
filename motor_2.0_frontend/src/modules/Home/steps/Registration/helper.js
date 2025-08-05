import moment from "moment";
import { toDate, reloadPage } from "utils";
import { differenceInMonths, differenceInDays } from "date-fns";
import swal from "sweetalert";
import _ from "lodash";
import { TypeReturn } from "modules/type";

//constants
//Fastlane/Ongrid constants
export function vahaanConstants(vahaanConfig, type) {
  return vahaanConfig?.data?.vahan?.registration?.[type] ?? false;
}

//BH is currently allowed in all brokers.
export const _blockBH = [];

//Helper functions
export const _isAndroidWebView = (temp_data) => {
  return (
    _.isEmpty(temp_data?.agentDetails?.filter((o) => o?.sellerType === "U")) &&
    _.isEmpty(temp_data?.agentDetails?.filter((o) => o?.source === "qr")) &&
    _.isEmpty(temp_data?.agentDetails?.filter((o) => o?.source === "ios")) &&
    window.location.hostname !== "localhost" &&
    import.meta.env.VITE_API_BASE_URL !==
      "https://apipreprodmotor.rbstaging.in/api"
  );
};

export const saod = (regDate, type, manufacturerDate) => {
  let b = "01-09-2018";
  let c = regDate;
  let d = moment().format("DD-MM-YYYY");
  let diffDaysOd = c && b && differenceInDays(toDate(c), toDate(b));
  let diffDaysManfOd = d && manufacturerDate && differenceInDays(toDate(d), toDate(manufacturerDate));
  let diffMonthsOdCar = c && d && differenceInMonths(toDate(d), toDate(c));
  let diffDayOd = c && d && differenceInDays(toDate(d), toDate(c));

  return (
    (diffDaysOd >= 0 && diffDayOd > 270 && TypeReturn(type) === "bike") ||
    (diffDayOd > 270 && diffMonthsOdCar < 34 && TypeReturn(type) === "car") || 
    (diffDayOd > 1 && diffMonthsOdCar < 9 && diffDaysManfOd > 270)
  );
};

//Calculate maxlength of registration number
export const _maxLength = (isBH, regIp, segmentInfo, MidsegmentInfo) => {
  return isBH
    ? //Bh series max length
      "12"
    : //General reg no max length
    regIp &&
      segmentInfo &&
      regIp.length > (Number(segmentInfo) === 1 ? 4 : 5) &&
      regIp.split("")[Number(segmentInfo) === 1 ? 5 : 6] * 1
    ? Number(segmentInfo) === 1
      ? "9"
      : "10"
    : Number(segmentInfo) === 1
    ? Number(MidsegmentInfo) === 1
      ? "11"
      : Number(MidsegmentInfo) === 2
      ? "12"
      : "13"
    : Number(MidsegmentInfo) === 1
    ? "12"
    : Number(MidsegmentInfo) === 2
    ? "13"
    : "14";
};

//Calculate mid-segment
export const isMidsegPresent = (regIp, midBlockCheck, segmentIndexes) => {
  return regIp &&
    !midBlockCheck &&
    segmentIndexes &&
    !_.isEmpty(segmentIndexes) &&
    segmentIndexes?.length >= 2
    ? segmentIndexes[2]?.length
    : "";
};

/*-----on paste-----*/
export const onPaste = (e, setValue, validate) => {
  if (!validate) {
    e.preventDefault();
  }
  let value = !validate
    ? e.clipboardData
        .getData("Text")
        .trim()
        .replace(/[^A-Za-z0-9]/gi, "")
    : e || "";
  //Auto correction for BH
  if (value && value[0] * 1) {
    return e.clipboardData;
  }
  //Auto correction for General reg no.
  else {
    let strlength = value.length;
    //formatting text
    let rtoState =
      Number(strlength) >= 2 &&
      value.slice(0, 2).match(/^[a-zA-Z]*$/) &&
      value.slice(0, 2);
    let rtoNum =
      Number(strlength) >= 4 && value.slice(2, 4).match(/^[0-9]*$/)
        ? value.slice(2, 4)
        : Number(strlength) >= 4 && value.slice(2, 3).match(/^[0-9]*$/)
        ? value.slice(2, 3)
        : "";
    let middleBlockStart = rtoNum ? rtoNum.length + 2 : false;
    //is middle block ineligible
    let middleblockcheck =
      rtoNum &&
      Number(strlength) >= middleBlockStart &&
      value.split("")[middleBlockStart] * 1;
    //middle block
    let middleblock =
      (rtoNum &&
        Number(strlength) >= middleBlockStart + 2 &&
        !middleblockcheck &&
        (Number(strlength) >= rtoNum.length + 5 &&
        value.slice(middleBlockStart, middleBlockStart + 3).match(/^[a-zA-Z]*$/)
          ? value.slice(middleBlockStart, middleBlockStart + 3)
          : value
              .slice(middleBlockStart, middleBlockStart + 2)
              .match(/^[a-zA-Z]*$/)
          ? value.slice(middleBlockStart, middleBlockStart + 2)
          : value
              .slice(middleBlockStart, middleBlockStart + 1)
              .match(/^[a-zA-Z]*$/)
          ? value.slice(middleBlockStart, middleBlockStart + 1)
          : "")) ||
      "";

    let lastBlock =
      !middleblockcheck && rtoNum && middleblock
        ? //with middle block
          Number(strlength) >= rtoNum.length + middleblock?.length + 3 &&
          (Number(strlength) >= rtoNum.length + middleblock?.length + 6 &&
          value
            .slice(
              rtoNum.length + middleblock?.length + 2,
              rtoNum.length + middleblock?.length + 6
            )
            .match(/^[0-9]*$/)
            ? value.slice(
                rtoNum.length + middleblock?.length + 2,
                rtoNum.length + middleblock?.length + 6
              )
            : Number(strlength) >= rtoNum.length + middleblock?.length + 5 &&
              value
                .slice(
                  rtoNum.length + middleblock?.length + 2,
                  rtoNum.length + middleblock?.length + 5
                )
                .match(/^[0-9]*$/)
            ? value.slice(
                rtoNum.length + middleblock?.length + 2,
                rtoNum.length + middleblock?.length + 5
              )
            : Number(strlength) >= rtoNum.length + middleblock?.length + 4 &&
              value
                .slice(
                  rtoNum.length + middleblock?.length + 2,
                  rtoNum.length + middleblock?.length + 4
                )
                .match(/^[0-9]*$/)
            ? value.slice(
                rtoNum.length + middleblock?.length + 2,
                rtoNum.length + middleblock?.length + 4
              )
            : value
                .slice(
                  rtoNum.length + middleblock?.length + 2,
                  rtoNum.length + middleblock?.length + 3
                )
                .match(/^[0-9]*$/)
            ? value.slice(
                rtoNum.length + middleblock?.length + 2,
                rtoNum.length + middleblock?.length + 3
              )
            : "")
        : //without middle block
          Number(strlength) >= rtoNum.length + 3 &&
          (Number(strlength) >= rtoNum.length + 6 &&
          value.slice(rtoNum.length + 2, rtoNum.length + 6).match(/^[0-9]*$/)
            ? value.slice(rtoNum.length + 2, rtoNum.length + 6)
            : Number(strlength) >= rtoNum.length + 5 &&
              value
                .slice(rtoNum.length + 2, rtoNum.length + 5)
                .match(/^[0-9]*$/)
            ? value.slice(rtoNum.length + 2, rtoNum.length + 5)
            : Number(strlength) >= rtoNum.length + 4 &&
              value
                .slice(rtoNum.length + 2, rtoNum.length + 4)
                .match(/^[0-9]*$/)
            ? value.slice(rtoNum.length + 2, rtoNum.length + 4)
            : Number(strlength) >= rtoNum.length + 3 &&
              value
                .slice(rtoNum.length + 2, rtoNum.length + 3)
                .match(/^[0-9]*$/)
            ? value.slice(rtoNum.length + 2, rtoNum.length + 3)
            : "");

    let finalRegNum =
      rtoState &&
      rtoNum &&
      `${rtoState}-${rtoNum}${middleblock ? `-${middleblock}` : ""}${
        lastBlock ? `-${lastBlock}` : ""
      }`;

    !validate && finalRegNum && setValue("regNo", finalRegNum.toUpperCase());
    return !validate ? false : finalRegNum;
  }
};
/*--x--on paste--x--*/

//Validations and key limitations
export const onChangeSingle = (e, segmentInfo, MidsegmentInfo, isBH) => {
  let value = e.target.value.trim().replace(/--/g, "-").toUpperCase();
  //check for BH series
  if (isBH) {
    /*--- BH series validations---*/
    //Reg Year should be eqal to or greater than 20
    if (value.length > 1 && value.length < 3) {
      e.target.value = e.target.value * 1 >= 20 ? e.target.value : "";
    }
    //value > 5 && value < 6 must be numbers. (serial block)
    if (value.length > 5 && value.length < 9) {
      e.target.value =
        e.target.value.slice(-1) * 1 || e.target.value.slice(-1) * 1 === 0
          ? e.target.value
          : e.target.value.slice(0, -1);
    }
    //value > 9 must be alphabets
    if (value.length > 9) {
      e.target.value =
        !(e.target.value.slice(-1) * 1) && e.target.value.slice(-1) * 1 !== 0
          ? e.target.value
          : e.target.value.slice(0, -1);
    }
    /*-x- BH series validations-x-*/
  }
  // general reg no
  else {
    //First two inputs must be Alphabets (eg. MH)
    if (value.length > 1 && value.length < 3) {
      e.target.value = e.target.value.replace(/[^A-Za-z\s]/gi, "");
    }
    //Next input must be a number
    if (value.length === 4) {
      e.target.value =
        Number(value.split("")[3] * 1) || Number(value.split("")[3] * 1) === 0
          ? e.target.value
          : e.target.value.slice(0, -1);
    }
    // Segment info denotes the length of the RTO Block. Eg. rto block can be MH-01 or DL-1.
    let middleBlock =
      value &&
      value.length > (Number(segmentInfo) === 1 ? 4 : 5) &&
      (value.split("")[Number(segmentInfo) === 1 ? 5 : 6] * 1 ||
        value.split("")[Number(segmentInfo) === 1 ? 5 : 6] * 1 === 0);
    if (value.length > 5) {
      // middle block denotes the part of RC number that comes after RTO Block
      // MH-04-AA-5757, in this string AA denotes the middle block
      //checking the numeric block if middle block is not present
      if (value.length > (Number(segmentInfo) === 1 ? 6 : 7) && middleBlock) {
        e.target.value =
          e.target.value.slice(-1) * 1 || e.target.value.slice(-1) * 1 === 0
            ? e.target.value
            : e.target.value.slice(0, -1);
      }
      //middle block two alphabet check.
      if (
        value.length > (Number(segmentInfo) === 1 ? 5 : 6) &&
        value.length < (Number(segmentInfo) === 1 ? 8 : 9) &&
        !middleBlock
      ) {
        //checking if the middle block's 1st entry is an alphabet
        if (value.length === (Number(segmentInfo) === 1 ? 6 : 7)) {
          e.target.value = e.target.value
            .split("")
            [Number(segmentInfo) === 1 ? 5 : 6].match(/^[a-zA-Z]*$/)
            ? e.target.value
            : e.target.value.slice(0, -1);
        }
        // checking if the middle block's 2nd entry is an alphabet & alphabet
        if (value.length === (Number(segmentInfo) === 1 ? 7 : 8)) {
          e.target.value = e.target.value
            .split("")
            [Number(segmentInfo) === 1 ? 6 : 7].match(/^[a-zA-Z0-9]*$/)
            ? e.target.value
            : e.target.value.slice(0, -1);
        }
      }
      //The last block entries must be numeric
      if (
        !middleBlock &&
        MidsegmentInfo &&
        value.length >
          (Number(segmentInfo) === 1
            ? Number(MidsegmentInfo) === 2
              ? 8
              : 9
            : Number(MidsegmentInfo) === 2
            ? 9
            : 10)
      ) {
        //letter numeric check
        e.target.value =
          e.target.value.slice(-1) * 1 || e.target.value.slice(-1) * 1 === 0
            ? e.target.value
            : e.target.value.slice(0, -1);
      }
    }
  }
};

//Controlling the registration string to autocorrect input
export const SingleKey = (e, inputParams, regInputStateParams) => {
  const { setValue, stepper1, setBuffer, onSubmitFastLane } = inputParams;
  const { temp_data, MidsegmentInfo, segmentInfo, isBH } = regInputStateParams;

  let value = e.target.value.trim().replace(/--/g, "-").toUpperCase();
  if (isBH) {
    /*-----Year block-----*/
    if (e.keyCode === 8 || e.keyCode === 46) {
      value = "";
    }
    if (value.length === 2 && !(e.keyCode === 8 || e.keyCode === 46)) {
      if (
        `20${value}` * 1 > new Date().getFullYear() * 1 ||
        `20${value}` * 1 < 2021
      ) {
        `20${value}` * 1 > new Date().getFullYear() * 1 &&
          swal("Registration year cannot be greater than current year").then(
            () => setValue("regNo", "")
          );
        `20${value}` * 1 < 2021 &&
          swal("Registration year cannot be lesser than 2021").then(() =>
            setValue("regNo", "")
          );
      } else {
        e.target.value = value + "BH-";
      }
    }
    /*--x--Year block--x--*/
    /*-----Serial block-----*/
    if (value.length === 9 && !(e.keyCode === 8 || e.keyCode === 46)) {
      e.target.value = value + "-";
    }
    /*--x--Serial block--x--*/
    if (value.length === 12 && ![37, 38, 39, 40, 8, 46].includes(e.keyCode)) {
      //onsubmit
      import.meta.env.VITE_BROKER !== "KAROINSURE" &&
        !stepper1 &&
        temp_data?.regNo !== value &&
        setBuffer(true);
      import.meta.env.VITE_BROKER !== "KAROINSURE" &&
        !stepper1 &&
        temp_data?.regNo !== value &&
        onSubmitFastLane();
    }
  } else {
    let middleBlock =
      value &&
      value.length > (Number(segmentInfo) === 1 ? 4 : 5) &&
      (value.split("")[Number(segmentInfo) === 1 ? 5 : 6] * 1 ||
        value.split("")[Number(segmentInfo) === 1 ? 5 : 6] * 1 === 0);
    //blocking keys when max len = e.target.value.length
    //handling blocks using length.
    /*-----rto block-----*/
    if (e.keyCode === 8 || e.keyCode === 46) {
      value = "";
    }
    if (value.length === 2 && !(e.keyCode === 8 || e.keyCode === 46)) {
      e.target.value = value.slice(-1) !== "-" ? value + "-" : value;
    }
    if (value.length === 5) {
      //if the user enters single serial after rto state then by hitting alphabet the user can generate next field
      if (
        !(value.slice(-1) * 1 || value.slice(-1) * 1 === 0) &&
        !value.slice(-2).includes("-")
      ) {
        //slicing the last char, adding "-" and appending the last char
        e.target.value = value.slice(0, -1) + "-" + value.slice(-1);
      } else {
        //arrow keys are excluded for navigation purposes
        if (![37, 38, 39, 40].includes(e.keyCode))
          e.target.value = value.slice(-1) === "-" ? value : value + "-";
      }
    }
    /*--x--rto block--x--*/

    /*-----Middle & Last Block-----*/
    //if the user enters an alphabet after rto block
    if (value.length > 5) {
      //first two entries of the midle block are validated in onchangesingle
      //the third entry in middle block can be a number or an alphabet.
      if (!middleBlock && (value?.length > Number(segmentInfo) === 1 ? 7 : 8)) {
        //if the 2nd entry is a number
        if (
          (value.split("")[Number(segmentInfo) === 1 ? 7 : 8] * 1 ||
            value.split("")[Number(segmentInfo) === 1 ? 7 : 8] * 1 === 0) &&
          !value.slice(-2).includes("-") &&
          value.split("")[Number(segmentInfo) === 1 ? 7 : 8] !== "-" &&
          value.split("")[Number(segmentInfo) === 1 ? 6 : 7] !== "-"
        ) {
          e.target.value = value.slice(0, -1) + "-" + value.slice(-1);
        }
        //if the 2nd entry is a number
        else if (
          !middleBlock &&
          (value.split("")[Number(segmentInfo) === 1 ? 6 : 7] * 1 ||
            value.split("")[Number(segmentInfo) === 1 ? 6 : 7] * 1 === 0) &&
          (value?.length > Number(segmentInfo) === 1 ? 6 : 7) &&
          !value.slice(-2).includes("-") &&
          value.split("")[Number(segmentInfo) === 1 ? 7 : 8] !== "-"
        ) {
          e.target.value = value.slice(0, -1) + "-" + value.slice(-1);
        } else {
          //last block generation , if all the entries are non number
          if (
            !middleBlock &&
            value?.length === (Number(segmentInfo) === 1 ? 8 : 9) &&
            value.split("")[Number(segmentInfo) === 1 ? 7 : 8] !== "-" &&
            value.split("")[Number(segmentInfo) === 1 ? 6 : 7] !== "-"
          ) {
            e.target.value = value.slice(-2) === "-" ? value : value + "-";
          }
        }
      }

      //onSubmit
      if (
        !middleBlock &&
        MidsegmentInfo &&
        value.length ===
          (Number(segmentInfo) === 1
            ? Number(MidsegmentInfo) === 1
              ? 11
              : Number(MidsegmentInfo) === 2
              ? 12
              : 13
            : Number(MidsegmentInfo) === 1
            ? 12
            : Number(MidsegmentInfo) === 2
            ? 13
            : 14) &&
        ![37, 38, 39, 40, 8, 46].includes(e.keyCode)
      ) {
        const triggerSubmit =
          !stepper1 &&
          value.match(
            /^[A-Z]{2}[-][0-9]{1,2}[-\s][A-Z0-9]{1,3}[-\s][0-9]{4}$/
          ) &&
          temp_data?.regNo !== value;
        //onsubmit
        import.meta.env.VITE_BROKER !== "KAROINSURE" &&
          triggerSubmit &&
          setBuffer(true);

        import.meta.env.VITE_BROKER !== "KAROINSURE" &&
          triggerSubmit &&
          onSubmitFastLane();
      }
      if (
        middleBlock &&
        value.length === (Number(segmentInfo) === 1 ? 9 : 10) &&
        ![37, 38, 39, 40, 8, 46].includes(e.keyCode)
      ) {
        const triggerSubmit =
          !stepper1 &&
          value.match(/^[A-Z]{2}[-][0-9]{1,2}[-\s][0-9]{4}$/) &&
          temp_data?.regNo !== value;
        //onsubmit
        import.meta.env.VITE_BROKER !== "KAROINSURE" &&
          triggerSubmit &&
          setBuffer(true);

        import.meta.env.VITE_BROKER !== "KAROINSURE" &&
          triggerSubmit &&
          onSubmitFastLane();
      }
      /*--x--Middle & Last Block--x--*/
    }
  }
};

export const _refocusOnReplace = (e) => {
  const inputValue = e.target.value;

  // Check if the input value contains '--' and matches certain patterns
  const isValidInput =
    inputValue?.includes("--") &&
    (inputValue
      .replace(/--/g, "-")
      .match(/^[A-Z]{2}[-][0-9]{1,2}[-\s][A-Z0-9]{1,3}[-\s][0-9]{4}$/) ||
      inputValue
        .replace(/--/g, "-")
        .match(/^[A-Z]{2}[-][0-9]{1,2}[-\s][0-9]{4}$/) ||
      (inputValue.replace(/--/g, "-") &&
        inputValue[0] * 1 &&
        inputValue.length > 10));

  if (isValidInput) {
    // Blur the input field with the name "regNo"
    document.querySelector(`input[name=regNo]`).blur();
  }

  // Modify the input value by removing spaces, replacing '--' with '-', and converting to uppercase
  e.target.value = inputValue
    .replace(/\s/gi, "")
    .replace(/--/g, "-")
    .toUpperCase();
};

export const _autoCorrection = (e, setValue, watch) => {
  return [
    onPaste(e, setValue),
    //returning formatted string
    e,
    //RC validation
    ((e?.target?.value &&
      (e?.target?.value?.match(
        /^[A-Z]{2}[-][0-9]{1,2}[-\s][A-Z0-9]{1,3}[-\s][0-9]{4}$/
      ) ||
        e?.target?.value?.match(/^[A-Z]{2}[-][0-9]{1,2}[-\s][0-9]{4}$/))) ||
      //BH series validation
      (e?.target?.value &&
        e?.target?.value[0] * 1 &&
        e?.target?.value.length > 10)) && [
      //setting value in input
      setValue("regNo", e?.target?.value),
      watch("regNo") &&
        watch("regNo") !== "--" &&
        //Triggering proceed with a delay to allow input population before execution
        import.meta.env.VITE_BROKER !== "KAROINSURE" &&
        setTimeout(
          () =>
            document.getElementById("proceedBtn") &&
            document.getElementById("proceedBtn")?.click(),
          20
        ),
    ],
  ];
};

//Enforce RB Login
export const enforceLogin = (setbtnDisable, setBuffer) => {
  swal(
    "Attention",
    "Login to experience the new & faster journey",
    "info"
  ).then(() => {
    setbtnDisable(false);
    setBuffer(false);
    document.getElementById("widgetJslogin") &&
      document.getElementById("widgetJslogin").click();
  });
};

export const isRegValid = (regIp, regNo1, regNo2, regNo3) => {
  return (
    (regNo1 &&
      regNo2 &&
      regNo3 &&
      `${regNo1}-${regNo2}-${regNo3}`.match(
        /^[A-Z]{2}[-][0-9]{1,2}[-\s][A-Z0-9]{1,3}[-\s][0-9]{4}$/
      )) ||
    (regNo1 &&
      !regNo2 &&
      regNo3 &&
      `${regNo1}-${regNo3}`.match(/^[A-Z]{2}[-][0-9]{1,2}[-\s][0-9]{4}$/)) ||
    (regIp && regIp[0] * 1)
  );
};

/*-----journey change-----*/
export const journeyMismatchFn = (
  frontendurl,
  TypeReturn,
  show,
  enquiry_id,
  token
) => {
  //Category Mismatch
  if (!["pcv", "gcv"].includes(show)) {
    if (frontendurl) {
      if (frontendurl?.car_frontend_url && TypeReturn(show) === "car") {
        reloadPage(
          `${
            frontendurl?.car_frontend_url
          }/registration?enquiry_id=${enquiry_id}${
            token ? `&xutm=${token}` : ""
          }`
        );
      } else if (
        frontendurl?.bike_frontend_url &&
        TypeReturn(show) === "bike"
      ) {
        reloadPage(
          `${
            frontendurl?.bike_frontend_url
          }/registration?enquiry_id=${enquiry_id}${
            token ? `&xutm=${token}` : ""
          }`
        );
      } else if (frontendurl?.cv_frontend_url && TypeReturn(show) === "cv") {
        reloadPage(
          `${
            frontendurl?.cv_frontend_url
          }/registration?enquiry_id=${enquiry_id}${
            token ? `&xutm=${token}` : ""
          }`
        );
      }
    }
  }
  //Sub section mismatch | eg. PCV RC used in GCV journey
  else {
    reloadPage(
      `${window.location.origin}${
        window.location.pathname
      }${window.location.search.replace(/gcv/g, show).replace(/pcv/g, show)}`
    );
  }
};

export const _bhCheck = (regIpCheck) => {
  return (
    regIpCheck &&
    regIpCheck.slice(0, 4).includes("BH") &&
    _blockBH.includes(import.meta.env.VITE_BROKER)
  );
};

export const noBack = (theme_conf, token, temp_data) => {
  return (
    !theme_conf?.broker_config?.noBack &&
    !token &&
    _.isEmpty(temp_data?.agentDetails?.filter((o) => o?.source === "qr"))
  );
};
