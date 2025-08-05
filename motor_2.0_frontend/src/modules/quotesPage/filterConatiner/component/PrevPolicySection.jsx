import React from "react";
import { Col } from "react-bootstrap";
import Style from "../style";
import moment from "moment";
import { subMonths, subYears } from "date-fns";
import { FiEdit } from "react-icons/fi";
import { Controller } from "react-hook-form";
import { ErrorMsg } from "components";
import DateInput from "modules/proposal/DateInput";

const PrevPolicySection = ({
  userData,
  location,
  type,
  reviewData,
  newCar,
  tempData,
  setPrevPopup,
  setEditDate,
  setEditInfoPopup,
  dateEditor,
  dateOutRef,
  control,
  policyMax,
  register,
  errors,
  isEditable,
}) => {
  return (
    <Col lg={3} md={12}>
      <Style.FilterMenuOpenWrap>
        <Style.FilterMenuOpenSub
          style={{ cursor: "pointer" }}
          onClick={
            import.meta.env.VITE_BROKER !== "BAJAJ" ||
            (userData?.temp_data?.corporateVehiclesQuoteRequest?.frontendTags &&
              import.meta.env.VITE_BROKER === "BAJAJ")
              ? () => {
                  document.getElementById("prevAndEditPopId") &&
                    document.getElementById("prevAndEditPopId").click !==
                      undefined &&
                    document.getElementById("prevAndEditPopId").click();
                }
              : () => {}
          }
        >
          PREVIOUS POLICY EXPIRY:{" "}
          <Style.FilterMenuOpenSubBold name="previous_policy_expiry">
            {
              <>
                {userData?.temp_data?.currentPolicyType === "newbusiness" ||
                newCar
                  ? "N/A"
                  : userData?.temp_data?.breakIn
                  ? userData?.temp_data?.expiry === "New" ||
                    moment(subMonths(new Date(Date.now()), 9)).format(
                      "DD-MM-YYYY"
                    ) === userData?.temp_data?.expiry
                    ? "N/A"
                    : userData?.temp_data?.expiry
                  : userData?.temp_data?.expiry || "N/A"}{" "}
                {isEditable &&
                  (userData?.temp_data?.expiry ||
                    userData?.temp_data?.corporateVehiclesQuoteRequest
                      ?.previousPolicyExpiryDate) &&
                  userData?.temp_data?.corporateVehiclesQuoteRequest
                    ?.previousPolicyExpiryDate !== "New" &&
                  userData?.temp_data?.expiry !== "New" && (
                    <FiEdit
                      className="blueIcon"
                      onClick={() => {
                        setPrevPopup(true);
                        setEditDate(true);
                      }}
                      id="prevAndEditPopId"
                    />
                  )}
              </>
            }{" "}
          </Style.FilterMenuOpenSubBold>
        </Style.FilterMenuOpenSub>
        <Style.FilterMenuOpenEdit>
          <Style.FilterMenuOpenTitle
            onClick={
              userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !==
                "Y" ||
              (userData?.temp_data?.corporateVehiclesQuoteRequest
                ?.frontendTags &&
                import.meta.env.VITE_BROKER === "BAJAJ")
                ? () => {
                    document.getElementById("regNoId") &&
                      document.getElementById("regNoId").click !== undefined &&
                      document.getElementById("regNoId").click();
                  }
                : () => {}
            }
          >
            INVOICE ON:{" "}
            <Style.FilterMenuOpenSubBold>
              {
                <span name="registered_on">
                  {userData?.temp_data?.vehicleInvoiceDate}{" "}
                </span>
              }
              {location.pathname === `/${type}/quotes` &&
                (isEditable || newCar) && (
                  <FiEdit
                    className="blueIcon"
                    onClick={() => setEditInfoPopup(true)}
                    id="regNoId"
                  />
                )}
              {dateEditor && (
                <div
                  className="py-2 dateTimeOne"
                  style={{
                    position: "relative",
                    bottom: "33px",
                  }}
                  ref={dateOutRef}
                >
                  <Controller
                    control={control}
                    name="regDate"
                    render={({ onChange, onBlur, value, name }) => (
                      <DateInput
                        filterDate
                        autoFocus={true}
                        maxDate={policyMax}
                        minDate={subYears(new Date(Date.now() - 86400000), 15)}
                        value={value}
                        name={name}
                        onChange={onChange}
                        ref={register}
                      />
                    )}
                  />
                  {!!errors.regDate && (
                    <ErrorMsg fontSize={"12px"}>
                      {errors.regDate.message}
                    </ErrorMsg>
                  )}
                </div>
              )}
            </Style.FilterMenuOpenSubBold>
          </Style.FilterMenuOpenTitle>
        </Style.FilterMenuOpenEdit>
      </Style.FilterMenuOpenWrap>
    </Col>
  );
};

export default PrevPolicySection;
