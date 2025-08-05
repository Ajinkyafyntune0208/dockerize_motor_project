import React, { useState, useEffect } from "react";
import { Row, Col } from "react-bootstrap";
import {
  differenceInMonths,
  differenceInDays,
  differenceInBusinessDays,
  format,
  subMonths,
  parse,
} from "date-fns";
import moment from "moment";
import _, { isNull, isEmpty } from "lodash";
import {
  DetailsSection,
  DetailsSectionLabel,
  StyledDatePicker,
} from "../styles";
import { vahaanServicesName } from "utils";
export const MMVSelection = ({
  Theme1,
  Controller,
  control,
  toDate,
  temp_data,
  DateInput,
  newRegDate,
  policyMin,
  policyMax,
  register,
  getYear,
  lessthan767,
  errors,
  ErrorMsg,
  manufactureDate,
  MultiSelect,
  insData,
  toDateOld,
  ownerType,
  owner,
  setValue,
  InvoiceDate,
  newManDate,
}) => {
  //-------------------------------prev ic logic and prefill--------------------------------

  const [prevIcData, setPrevIcData] = useState(false);
  // ------ logic( only show prev ic option to change when it is selcted once )-----------------------

  useEffect(() => {
    if (
      temp_data?.prevIc &&
      !temp_data?.newCar &&
      temp_data?.prevIc !== "NEW" &&
      temp_data?.prevIc !== "New"
    ) {
      setPrevIcData(true);
    } else {
      setPrevIcData(false);
    }
  }, [temp_data?.prevIc]);

  useEffect(() => {
    if (temp_data?.ownerTypeId && !owner) {
      let selectedOption =
        ownerType.filter((x) => x.value * 1 === temp_data?.ownerTypeId * 1) ||
        [];
      !isEmpty(selectedOption) && setValue("ownerType", selectedOption[0]);
    }
  }, [temp_data?.ownerTypeId, ownerType]);

  //-------------------resetting man data nd its max and min limits when reg date value changes -------------------------
  const handleValueChange = (date) => {
    let newDate = moment(date).format("DD-MM-YYYY");
    if (newDate && manufactureDate) {
      let differenceInMonthsMan = differenceInMonths(
        toDate(newDate),
        toDate(manufactureDate)
      );

      let differneceInDaysMan = differenceInDays(
        toDate(newDate),
        toDate(manufactureDate)
      );

      if (
        differenceInMonthsMan < 0 ||
        differenceInMonthsMan > 36 ||
        differneceInDaysMan < 0
      ) {
        setValue("date2", "");
        setValue("vehicleInvoiceDate", "");
      }
      if (
        !(
          toDate(newDate || temp_data?.regDate) >=
          toDate(InvoiceDate || temp_data?.vehicleInvoiceDate)
        )
      ) {
        setValue("vehicleInvoiceDate", "");
      } else if (
        !(
          toDate(InvoiceDate || temp_data?.vehicleInvoiceDate) >=
          toDate(manufactureDate || temp_data?.manfDate)
        )
      ) {
        setValue("vehicleInvoiceDate", "");
      }
    }
  };

  const handleManufactureValueChange = (date) => {
    let newMafDate = moment(date).format("DD-MM-YYYY");
    if (
      toDate(InvoiceDate || temp_data?.vehicleInvoiceDate) <
      toDate(newMafDate || temp_data?.manfDate)
    ) {
      setValue("vehicleInvoiceDate", "");
    }
  };
  //-----------------------------prefill man date and reg date------------------
  useEffect(() => {
    if (temp_data?.manfDate) setValue("date2", temp_data?.manfDate);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    if (temp_data?.regDate) setValue("date1", temp_data?.regDate);
  }, [temp_data?.manfDate, temp_data?.regDate]);

  //default invoice date selected condition
  const invoiceSelected = _.isEmpty(InvoiceDate)
    ? false
    : (true && InvoiceDate) || temp_data?.vehicleInvoiceDate
    ? toDate(InvoiceDate || temp_data?.vehicleInvoiceDate)
    : false;

  //default registration date selected condition
  const mafDateSelected = _.isEmpty(newManDate)
    ? false
    : (true && newRegDate) || temp_data?.regDate
    ? toDate(manufactureDate || `01-${temp_data?.manfDate}`)
    : false;

  const regSingleYear = temp_data?.regNo
    ? temp_data?.regNo[0] * 1
      ? temp_data?.regNo.slice(0, 2)
      : false
    : false;

  const readOnly =
    temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y" &&
    temp_data?.corporateVehiclesQuoteRequest?.businessType === "breakin";

  //registration date and manufacture date non-editable in vaahan services
  const nonEditableVaahan = vahaanServicesName?.includes(
    temp_data?.corporateVehiclesQuoteRequest?.journeyType
  );

  //Making the registration date non-editable in the new business break-in logic, where the difference between the current date and the invoice date is less than 9 months, and the difference between the current date and the manufacturing date is less than 270 days.
  // const newBreakinCondition =
  //   manufactureDate &&
  //   temp_data?.manfDate &&
  //   differenceInMonths(
  //     new Date(),
  //     toDate(InvoiceDate || temp_data?.vehicleInvoiceDate)
  //   ) < 9 &&
  //   differenceInDays(
  //     new Date(),
  //     toDate(manufactureDate || `01-${temp_data?.manfDate}`)
  //   ) > 270;

    
  return (
    <>
      <DetailsSection>
        <Row>
          <Col md={4} sm={12}>
            <DetailsSectionLabel>Registration Date</DetailsSectionLabel>
          </Col>
          <Col md={8} sm={12}>
            <StyledDatePicker Theme1={Theme1}>
              <div className="py-2 dateTimeOne " style={{}}>
                <Controller
                  control={control}
                  name="date1"
                  defaultValue={temp_data?.regDate}
                  render={({ onChange, onBlur, value, name }) => (
                    <DateInput
                      editPopupDate
                      value={value}
                      selected={
                        newRegDate || temp_data?.regDate
                          ? toDate(newRegDate || temp_data?.regDate)
                          : false
                      }
                      singleYear={regSingleYear}
                      minDate={policyMin}
                      maxDate={policyMax}
                      name={name}
                      onChange={onChange}
                      ref={register}
                      onValueChange={(date) => {
                        handleValueChange(date);
                      }}
                      rangeMax={
                        !temp_data?.newCar
                          ? getYear(new Date(Date.now()) + 1)
                          : false
                      }
                      readOnly={
                        readOnly || temp_data?.newCar || nonEditableVaahan
                      }
                      withPortal={lessthan767 ? true : false}
                    />
                  )}
                />
                {!!errors.date1 && (
                  <ErrorMsg fontSize={"12px"}>{errors.date1.message}</ErrorMsg>
                )}
              </div>
            </StyledDatePicker>
          </Col>
        </Row>
      </DetailsSection>
      <DetailsSection>
        <Row>
          <Col md={4} sm={12}>
            <DetailsSectionLabel>Manufacture Month-Year</DetailsSectionLabel>
          </Col>
          <Col md={8} sm={12}>
            <StyledDatePicker Theme1={Theme1} errors={errors.date2}>
              <div className="py-2 dateTimeOne " style={{}}>
                <Controller
                  control={control}
                  name="date2"
                  defaultValue={temp_data?.manfDate}
                  render={({ onChange, onBlur, value, name }) => (
                    <DateInput
                      editPopupDate
                      placeholderText={"Select a month & year"}
                      selected={mafDateSelected}
                      maxDate={
                        newRegDate || temp_data?.regDate
                          ? toDate(newRegDate || temp_data?.regDate)
                          : false
                      }
                      minDate={
                        newRegDate || temp_data?.regDate
                          ? toDateOld(newRegDate || temp_data?.regDate)
                          : false
                      }
                      onValueChange={(date) => {
                        handleManufactureValueChange(date);
                      }}
                      value={value}
                      name={name}
                      onChange={onChange}
                      ref={register}
                      rangeMax={
                        !temp_data?.newCar
                          ? getYear(new Date(Date.now()) + 1)
                          : false
                      }
                      showMonthYearPicker={true}
                      dateFormat={"MM/yyyy"}
                      withPortal={lessthan767 ? true : false}
                      readOnly={readOnly || nonEditableVaahan}
                    />
                  )}
                />
              </div>
            </StyledDatePicker>
          </Col>
        </Row>
      </DetailsSection>
      <DetailsSection>
        <Row>
          <Col md={4} sm={12}>
            <DetailsSectionLabel>Invoice Date</DetailsSectionLabel>
          </Col>
          <Col md={8} sm={12}>
            <StyledDatePicker
              Theme1={Theme1}
              errors={errors.vehicleInvoiceDate}
            >
              <div className="py-2 dateTimeOne " style={{}}>
                <Controller
                  control={control}
                  name="vehicleInvoiceDate"
                  defaultValue={temp_data?.vehicleInvoiceDate}
                  render={({ onChange, onBlur, value, name }) => (
                    <DateInput
                      editPopupDate
                      value={value}
                      placeholderText={"Select invoice date"}
                      selected={invoiceSelected}
                      singleYear={regSingleYear}
                      minDate={
                        manufactureDate || temp_data?.manfDate
                          ? toDate(manufactureDate || temp_data?.manfDate)
                          : false
                      }
                      maxDate={
                        newRegDate || temp_data?.regDate
                          ? toDate(newRegDate || temp_data?.regDate)
                          : false
                      }
                      name={name}
                      onChange={onChange}
                      ref={register}
                      // onValueChange={(date) => {
                      //   handleInvoiceValueChange(date);
                      // }}
                      rangeMax={
                        !temp_data?.newCar
                          ? getYear(new Date(Date.now()) + 1)
                          : false
                      }
                      readOnly={
                        readOnly ||
                        (!newRegDate && !manufactureDate) ||
                        temp_data?.newCar
                      }
                      withPortal={lessthan767 ? true : false}
                    />
                  )}
                />
                {/* {!!errors.vehicleInvoiceDate && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors.vehicleInvoiceDate.message}
                  </ErrorMsg>
                )} */}
              </div>
            </StyledDatePicker>
          </Col>
        </Row>
      </DetailsSection>
      <DetailsSection>
        <Row>
          <Col md={4} sm={12}>
            <DetailsSectionLabel>Owner Type </DetailsSectionLabel>
          </Col>
          <Col
            md={8}
            sm={12}
            className="dropDownColomn"
            style={{ ...(readOnly && { pointerEvents: "none" }) }}
          >
            <Controller
              control={control}
              name="ownerType"
              // defaultValue={""}
              render={({ onChange, onBlur, value, name }) => (
                <MultiSelect
                  quotes
                  knowMore
                  name={name}
                  onChange={onChange}
                  ref={register}
                  value={value}
                  onBlur={onBlur}
                  isMulti={false}
                  options={ownerType}
                  placeholder={"Owner type"}
                  errors={errors.ownerType}
                  Styled
                  closeOnSelect
                  readOnly={readOnly}
                />
              )}
            />
          </Col>
        </Row>
      </DetailsSection>
      <div className={!prevIcData ? "hiddenInput" : ""}>
        <DetailsSection>
          <Row>
            <Col md={4} sm={12}>
              <DetailsSectionLabel>Previous Ic </DetailsSectionLabel>
            </Col>
            <Col md={8} sm={12} style={{}} className="dropDownColomn">
              <Controller
                control={control}
                name="preIc"
                defaultValue={""}
                render={({ onChange, onBlur, value, name }) => (
                  <MultiSelect
                    quotes
                    knowMore
                    name={name}
                    onChange={onChange}
                    ref={register}
                    value={value}
                    onBlur={onBlur}
                    isMulti={false}
                    options={insData}
                    placeholder={"Previous Insurer"}
                    errors={errors.preIc}
                    Styled
                    closeOnSelect
                  />
                )}
              />
            </Col>
          </Row>
        </DetailsSection>
      </div>
    </>
  );
};
