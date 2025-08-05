import React from "react";
import { DetailsSection, DetailsSectionLabel } from "../styles";
import { MultiSelect, Error } from "components";
import { Row, Col } from "react-bootstrap";
import { vahaanServicesName } from "utils";
export const VehicleDetails = ({
  temp_data,
  Controller,
  control,
  register,
  carrierType,
  errors,
  Brands,
  dispatch,
  emptyModelType,
  emptyVariant,
  setFuelType,
  setValue,
  Fuel,
  RTO,
  Models,
  model,
  Options,
}) => {
  const journeyTypeCheck =
    vahaanServicesName?.includes(
      temp_data?.corporateVehiclesQuoteRequest?.journeyType
    ) && ["KAROINSURE"].includes(import.meta.env.VITE_BROKER)
      ? { pointerEvents: "none" }
      : {};

  return (
    <>
      {temp_data?.journeyCategory === "GCV" && (
        <DetailsSection>
          <Row>
            <Col md={4} sm={12}>
              <DetailsSectionLabel>Vehicle Carrier Type </DetailsSectionLabel>
            </Col>
            <Col
              md={8}
              sm={12}
              style={{}}
              className="dropDownColomn"
              id="carrier"
            >
              <Controller
                control={control}
                name="carrierType"
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
                    options={carrierType}
                    placeholder={"Vehicle Carrier Type"}
                    errors={errors.carrierType}
                    Styled
                    closeOnSelect
                  />
                )}
              />
              {!!errors?.carrierType && (
                <Error className="mt-1">{errors?.carrierType?.message}</Error>
              )}
            </Col>
          </Row>
        </DetailsSection>
      )}
      <DetailsSection>
        <Row>
          <Col md={4} sm={12}>
            <DetailsSectionLabel>Brand </DetailsSectionLabel>
          </Col>
          <Col
            md={8}
            sm={12}
            className="dropDownColomn"
            id="brand"
            style={journeyTypeCheck}
          >
            <Controller
              control={control}
              name="brand"
              // defaultValue={temp_data?.fuel}
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
                  options={Brands}
                  placeholder={"Brand"}
                  errors={errors.brand}
                  Styled
                  closeOnSelect
                  onValueChange={() => {
                    dispatch(emptyModelType([]));
                    dispatch(emptyVariant([]));
                    dispatch(setFuelType([]));
                    setValue("model", null);
                    setValue("fuel", null);
                    setValue("variant", null);
                  }}
                />
              )}
            />
            {!!errors?.brand && (
              <Error className="mt-1">{errors?.brand?.message}</Error>
            )}
          </Col>
        </Row>
      </DetailsSection>
      <DetailsSection>
        <Row>
          <Col md={4} sm={12}>
            <DetailsSectionLabel>Model </DetailsSectionLabel>
          </Col>
          <Col
            md={8}
            sm={12}
            style={journeyTypeCheck}
            className="dropDownColomn"
            id="model"
          >
            <Controller
              control={control}
              name="model"
              // defaultValue={temp_data?.fuel}
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
                  options={Models}
                  placeholder={"Model"}
                  errors={errors.model}
                  Styled
                  closeOnSelect
                  onValueChange={() => {
                    setValue("fuel", null);
                    setValue("variant", null);
                  }}
                />
              )}
            />
            {!!errors?.model && (
              <Error className="mt-1">{errors?.model?.message}</Error>
            )}
          </Col>
        </Row>
      </DetailsSection>

      <DetailsSection>
        <Row>
          <Col md={4} sm={12}>
            <DetailsSectionLabel>Fuel Type </DetailsSectionLabel>
          </Col>
          <Col
            md={8}
            sm={12}
            style={journeyTypeCheck}
            className="dropDownColomn"
            id="fuel"
          >
            <Controller
              control={control}
              name="fuel"
              render={({ onChange, onBlur, value, name }) => (
                <MultiSelect
                  quotes
                  knowMore
                  name={name}
                  onChange={onChange}
                  ref={register}
                  value={Fuel?.length === 1 ? Fuel[0] : value}
                  onBlur={onBlur}
                  isMulti={false}
                  options={Fuel}
                  placeholder={
                    !model
                      ? "Select Fuel"
                      : Fuel.length > 0
                      ? "Fuel"
                      : "Loading..."
                  }
                  errors={errors.Fuel}
                  Styled
                  closeOnSelect
                  onValueChange={() => setValue("variant", null)}
                />
              )}
            />
            {!!errors?.fuel && (
              <Error className="mt-1">{errors?.fuel?.message}</Error>
            )}
          </Col>
        </Row>
      </DetailsSection>

      <DetailsSection>
        <Row>
          <Col md={4} sm={12}>
            <DetailsSectionLabel>Variant </DetailsSectionLabel>
          </Col>
          <Col
            md={8}
            sm={12}
            style={{}}
            className="dropDownColomn"
            id="version"
          >
            <Controller
              control={control}
              name="variant"
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
                  options={Options}
                  placeholder={"Select Variant"}
                  errors={errors.variant}
                  Styled
                  closeOnSelect
                />
              )}
            />
          </Col>
        </Row>
      </DetailsSection>

      {!temp_data?.regNo ||
      temp_data?.regNo === "NEW" ||
      (temp_data?.regNo && temp_data?.regNo[0] * 1) ? (
        <DetailsSection>
          <Row>
            <Col md={4} sm={12}>
              <DetailsSectionLabel>RTO </DetailsSectionLabel>
            </Col>
            <Col md={8} sm={12} style={{}} className="dropDownColomn" id="rto">
              <Controller
                control={control}
                name="rto"
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
                    options={RTO}
                    placeholder={"Select RTO"}
                    errors={errors.rto}
                    Styled
                    closeOnSelect
                    rto
                  />
                )}
              />
            </Col>
          </Row>
        </DetailsSection>
      ) : (
        <noscript />
      )}
    </>
  );
};
