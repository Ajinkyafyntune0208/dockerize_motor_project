import React from "react";
import { Col } from "react-bootstrap";
import Style from "../style";
import moment from "moment";
import { differenceInDays, subMonths } from "date-fns";
import { FiEdit } from "react-icons/fi";
import CustomTooltip from "components/tooltip/CustomTooltip";
import tooltip from "assets/img/tooltip.svg";
import { toDate } from "utils";

const NcbSection = ({
  userData,
  location,
  type,
  reviewData,
  tempData,
  newCar,
  setNcbPopup,
}) => {
  const previousNcb =
    userData.temp_data?.newCar ||
    userData?.temp_data?.expiry === "New" ||
    moment(subMonths(new Date(Date.now()), 9)).format("DD-MM-YYYY") ===
      userData?.temp_data?.expiry ||
    (userData?.temp_data?.expiry &&
      differenceInDays(
        toDate(moment().format("DD-MM-YYYY")),
        toDate(userData?.temp_data?.expiry)
      ) > 90)
      ? "N/A"
      : userData.temp_data?.tab === "tab2"
      ? "0%"
      : userData.temp_data?.ncb || "0%";

  const newNcb = userData.temp_data?.newNcb
    ? userData.temp_data?.tab === "tab2" ||
      (userData?.temp_data?.expiry &&
        differenceInDays(
          toDate(moment().format("DD-MM-YYYY")),
          toDate(userData?.temp_data?.expiry)
        ) > 90)
      ? "N/A"
      : userData.temp_data?.newCar
      ? "0%"
      : userData?.temp_data?.newNcb
    : "0%";

  const isNcbEditable =
    // userData.temp_data?.tab !== "tab2" &&
    // tempData.policyType !== "Third-party" &&
    !newCar &&
    userData?.temp_data?.expiry &&
    //Renewal config
    (userData?.temp_data?.renewalAttributes?.ncb ||
      userData?.temp_data?.renewalAttributes?.claim ||
      userData?.temp_data?.renewalAttributes?.ownership ||
      userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !== "Y");

  return (
    <Col lg={3} md={12}>
      <Style.FilterMenuOpenWrap>
        <Style.FilterMenuOpenSub
          onClick={() =>
            isNcbEditable &&
            document.getElementById("ncbPopupId") &&
            document.getElementById("ncbPopupId").click &&
            document.getElementById("ncbPopupId").click()
          }
        >
          PREVIOUS NCB
          <span style={{ fontSize: "10px" }}>
            {userData.temp_data?.isNcbVerified !== "Y" ? (
              <CustomTooltip
                rider="true"
                id={`ncbAssumption`}
                place={"left"}
                customClassName="mt-3 "
                allowClick
              >
                <text
                  data-tip={`<div>The NCB applicable evaluated basis of vehicle age and assumption that there is no claim raised till date from date of registration.</div>`}
                  data-html={true}
                  data-for={`ncbAssumption`}
                  src={tooltip}
                  alt="tooltip"
                >
                  (Assumed)
                </text>
              </CustomTooltip>
            ) : (
              ""
            )}
            :{" "}
          </span>
          <Style.FilterMenuOpenSubBold name="ncb">
            {
              <>
                <b>{previousNcb}</b>{" "}
              </>
            }
            {isNcbEditable && (
              <FiEdit
                className="blueIcon"
                onClick={() => setNcbPopup(true)}
                id="ncbPopupId"
              />
            )}
          </Style.FilterMenuOpenSubBold>
        </Style.FilterMenuOpenSub>
        <Style.FilterMenuOpenEdit>
          <Style.FilterMenuOpenTitle>
            NEW NCB:{" "}
            <Style.FilterMenuOpenSubBold name="ncb">
              {<> {newNcb}</>}
            </Style.FilterMenuOpenSubBold>
          </Style.FilterMenuOpenTitle>
        </Style.FilterMenuOpenEdit>
      </Style.FilterMenuOpenWrap>
    </Col>
  );
};

export default NcbSection;
